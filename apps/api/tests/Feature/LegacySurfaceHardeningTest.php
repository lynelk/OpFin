<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\Account;
use App\Models\CreditScore;
use App\Models\CreditScoreComponent;
use App\Models\FloatTopup;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\ExternalScoringService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacySurfaceHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_credit_bureau_and_nin_endpoints_are_removed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/credit-scores', ['phone_number' => '256700000000'])->assertNotFound();
        $this->postJson('/api/validate-nin', ['nin' => 'CM12345678901A'])->assertNotFound();
    }

    public function test_unattributed_legacy_bureau_scores_do_not_feed_the_composite_score(): void
    {
        Http::fake();
        $user = User::factory()->create();

        // Written by the retired endpoint: a raw bureau payload for whatever phone was submitted.
        CreditScore::create(['user_id' => $user->id, 'score' => 780, 'band' => 'A', 'data' => ['data' => ['CRB' => ['Scoring' => ['Score' => 780]]]]]);

        $component = app(ExternalScoringService::class)->refresh($user)['crb'];
        $this->assertNotSame(CreditScoreComponent::STATUS_READY, $component->status);
        $this->assertNull($component->score);

        // Obtained through the governed route and therefore attributable.
        CreditScore::create(['user_id' => $user->id, 'score' => 650, 'band' => 'B', 'data' => ['route' => 'CITO_MANAGED', 'cito_reference' => 'cito-ref-1']]);

        $component = app(ExternalScoringService::class)->refresh($user)['crb'];
        $this->assertSame(CreditScoreComponent::STATUS_READY, $component->status);
        $this->assertSame('legacy_cached', $component->raw_payload['route']);
        $this->assertEquals(65.0, (float) $component->score);
    }

    public function test_otp_sms_is_stored_redacted_and_only_sent_through_the_encrypted_job(): void
    {
        Queue::fake();

        $this->postJson('/api/generate-otp', ['phone' => '256700123456'])->assertOk();

        $stored = SmsMessage::sole();
        $this->assertStringContainsString(SmsMessage::REDACTED, $stored->message);
        $this->assertDoesNotMatchRegularExpression('/\d{6}/', $stored->message);

        Queue::assertPushed(SendSms::class, function (SendSms $job): bool {
            return preg_match('/OTP code is \d{6}\./', $job->content()) === 1;
        });
        $this->assertContains(ShouldBeEncrypted::class, class_implements(SendSms::class));
    }

    public function test_existing_sms_records_have_their_codes_redacted(): void
    {
        $otp = SmsMessage::create(['to' => '256700000001', 'message' => 'OpFin: Your OTP code is 012345. It expires in 5 minutes.', 'status' => 'Sent']);
        $guarantor = SmsMessage::create(['to' => '256700000002', 'message' => 'If you agree, give code 654321 to the borrower.', 'status' => 'Sent']);
        $receipt = SmsMessage::create(['to' => '256700000003', 'message' => 'Payment of UGX 250000 received for loan 1234.', 'status' => 'Sent']);

        (require database_path('migrations/2026_10_03_090000_redact_codes_in_sms_messages.php'))->up();

        $this->assertSame('OpFin: Your OTP code is ******. It expires in 5 minutes.', $otp->fresh()->message);
        $this->assertSame('If you agree, give code ****** to the borrower.', $guarantor->fresh()->message);
        $this->assertSame('Payment of UGX 250000 received for loan 1234.', $receipt->fresh()->message);
    }

    public function test_legacy_back_office_requires_a_staff_role(): void
    {
        $customer = User::factory()->create();

        foreach (['/home', '/users', '/float-management', '/sms-messages', '/transactions', '/accounts', '/loans'] as $path) {
            $this->actingAs($customer)->get($path)->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OPERATIONS]))
            ->get('/sms-messages')
            ->assertOk();
    }

    public function test_only_platform_administrators_create_back_office_users_and_never_with_a_default_password(): void
    {
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $payload = ['name' => 'Branch Member', 'phone' => '256711000111', 'role' => 'Member'];

        $this->actingAs($operations)->post('/users', $payload)->assertForbidden();
        $this->assertDatabaseMissing('users', ['phone' => '256711000111']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]))
            ->post('/users', $payload)
            ->assertRedirect(route('users.index'));

        $created = User::where('phone', '256711000111')->sole();
        $this->assertSame(User::ROLE_CUSTOMER, $created->role);
        $this->assertFalse(Hash::check('Password@123', $created->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'backoffice.user.created']);
    }

    public function test_back_office_cannot_overwrite_a_staff_members_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $institution = DB::table('institutions')->insertGetId(['name' => 'Lender', 'address' => 'Kampala', 'phone' => '256700000009', 'email' => 'lender@example.test', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->put("/users/{$operations->id}", [
            'name' => 'Changed', 'phone' => $operations->phone, 'institution_id' => $institution, 'role' => 'Member',
        ])->assertForbidden();

        $this->assertSame(User::ROLE_OPERATIONS, $operations->fresh()->role);
    }

    public function test_float_top_up_needs_a_second_staff_member_before_the_balance_changes(): void
    {
        $account = Account::create(['name' => 'Disbursement', 'balance' => 1000]);
        $maker = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);

        $this->actingAs($maker)->post('/float-management', ['amount' => '500.50'])->assertRedirect();
        $topup = FloatTopup::sole();
        $this->assertSame(FloatTopup::STATUS_PENDING, $topup->status);
        $this->assertSame((int) $maker->id, (int) $topup->recorded_by_user_id);
        $this->assertEquals(1000, (float) $account->fresh()->balance);

        $this->actingAs($maker)->post("/float-management/{$topup->id}/approve")->assertSessionHas('error');
        $this->assertSame(FloatTopup::STATUS_PENDING, $topup->fresh()->status);
        $this->assertEquals(1000, (float) $account->fresh()->balance);

        $this->actingAs($checker)->post("/float-management/{$topup->id}/approve")->assertSessionHas('success');
        $this->assertSame(FloatTopup::STATUS_APPROVED, $topup->fresh()->status);
        $this->assertSame((int) $checker->id, (int) $topup->fresh()->approved_by_user_id);
        $this->assertEquals(1500.50, (float) $account->fresh()->balance);

        $this->actingAs($checker)->post("/float-management/{$topup->id}/approve")->assertSessionHas('error');
        $this->assertEquals(1500.50, (float) $account->fresh()->balance);
        $this->assertDatabaseHas('audit_logs', ['event' => 'float_topup.approved']);
    }

    public function test_ai_chat_routes_are_retired(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));

        $this->get('/chats')->assertNotFound();
        $this->get('/chats/1/stream?question=hello')->assertNotFound();
    }

    public function test_legacy_account_deletion_forwards_to_the_web_journey(): void
    {
        config(['services.opfin.web_url' => 'https://web.opfin.test/']);

        $this->get('/account/delete')->assertRedirect('https://web.opfin.test/account/delete')->assertStatus(303);
        $this->delete('/account/delete', ['phone' => '256700000000', 'pin' => '123456', 'confirmation' => 'DELETE'])
            ->assertRedirect('https://web.opfin.test/account/delete');

        config(['services.opfin.web_url' => null]);
        $this->get('/account/delete')->assertNotFound();
    }

    public function test_legacy_roles_are_normalised_and_institution_administrators_wait_for_review(): void
    {
        $super = User::factory()->create(['role' => 'Super']);
        $client = User::factory()->create(['role' => 'Client']);
        $member = User::factory()->create(['role' => 'Member']);
        $institutionAdmin = User::factory()->create(['role' => 'Admin']);
        $institutionAdmin->createToken('legacy');

        $this->artisan('opfin:legacy-roles')->assertExitCode(0);

        (require database_path('migrations/2026_10_03_090200_normalise_legacy_user_roles.php'))->up();

        $this->assertSame(User::ROLE_PLATFORM_ADMIN, $super->fresh()->role);
        $this->assertSame(User::ROLE_CUSTOMER, $client->fresh()->role);
        $this->assertSame(User::ROLE_CUSTOMER, $member->fresh()->role);
        $this->assertSame(User::ROLE_STAFF_PENDING_REVIEW, $institutionAdmin->fresh()->role);
        $this->assertSame(0, $institutionAdmin->tokens()->count());
        $this->assertSame(4, DB::table('audit_logs')->where('event', 'user.role.legacy_normalised')->count());

        $this->assertSame(User::ROLE_CUSTOMER, User::query()->create(['name' => 'Default', 'phone' => '256799999999', 'password' => 'x'])->fresh()->role);
    }

    public function test_staff_awaiting_access_review_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF_PENDING_REVIEW,
            'password' => Hash::make('482915'),
        ]);

        $this->postJson('/api/login', ['phone' => $user->phone, 'pin' => '482915'])
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertSame(0, $user->tokens()->count());
    }
}
