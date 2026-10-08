<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCode;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffCredentialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_TIME = 'Temp@2468';

    private const STRONG = 'Correct-Horse-42!';

    public function test_bootstrap_creates_the_owner_admin_with_a_one_time_password_and_is_idempotent(): void
    {
        $this->bootstrap()->assertSuccessful();

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_PLATFORM_ADMIN, $admin->role);
        $this->assertSame('256700000123', $admin->phone);
        $this->assertTrue($admin->password_change_required);
        $this->assertTrue(Hash::check(self::ONE_TIME, $admin->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.admin_bootstrapped', 'subject_id' => $admin->id]);

        $this->artisan('opfin:admin:bootstrap', ['--email' => 'owner@example.test'])
            ->expectsOutputToContain('already a platform administrator')->assertSuccessful();
        $this->assertTrue(Hash::check(self::ONE_TIME, $admin->fresh()->password));
    }

    public function test_bootstrap_refuses_weak_or_mismatched_passwords_and_promotes_an_existing_account(): void
    {
        $this->artisan('opfin:admin:bootstrap', ['--email' => 'owner@example.test', '--phone' => '0700000123'])
            ->expectsQuestion('One-time password (hidden)', 'short')->assertFailed();
        $this->artisan('opfin:admin:bootstrap', ['--email' => 'owner@example.test', '--phone' => '0700000123'])
            ->expectsQuestion('One-time password (hidden)', self::ONE_TIME)->expectsQuestion('Type it again', 'Other@1357')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);

        $other = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'phone' => '256700000123', 'email' => 'someone-else@example.test']);
        $this->artisan('opfin:admin:bootstrap', ['--email' => 'owner@example.test', '--phone' => '256700000123'])
            ->expectsOutputToContain('already uses a different email')->assertFailed();
        $other->forceDelete();

        $existing = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'phone' => '256700000123', 'email' => null]);
        $this->bootstrap()->assertSuccessful();
        $this->assertSame(User::ROLE_PLATFORM_ADMIN, $existing->fresh()->role);
        $this->assertSame('owner@example.test', $existing->fresh()->email);
    }

    public function test_email_sign_in_is_for_staff_only_and_forces_a_password_change_first(): void
    {
        $this->bootstrap();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'customer@example.test', 'password' => self::STRONG]);

        $this->postJson('/api/login', ['email' => 'customer@example.test', 'password' => self::STRONG])
            ->assertUnauthorized()->assertJsonPath('message', 'Email or password is incorrect.');
        $this->postJson('/api/login', ['email' => 'nobody@example.test', 'password' => self::STRONG])
            ->assertUnauthorized()->assertJsonPath('message', 'Email or password is incorrect.');
        $this->assertNotNull($customer->id);

        $token = $this->postJson('/api/login', ['email' => 'Owner@Example.test', 'password' => self::ONE_TIME])->assertOk()
            ->assertJsonPath('data.password_change_required', true)->json('data.access_token');

        $this->withToken($token)->getJson('/api/capabilities')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->withToken($token)->getJson('/api/profile')->assertOk();
        $this->withToken($token)->postJson('/api/account/password', ['current_password' => self::ONE_TIME, 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertUnprocessable();
        $this->withToken($token)->postJson('/api/account/password', ['current_password' => self::ONE_TIME, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertOk()->assertJsonPath('data.password_change_required', false);

        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertFalse($admin->password_change_required);
        $this->assertNotNull($admin->password_changed_at);
        $this->withToken($token)->getJson('/api/capabilities')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_changed', 'subject_id' => $admin->id]);
    }

    public function test_email_sign_in_locks_after_five_failures(): void
    {
        $this->bootstrap();
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'owner@example.test', 'password' => 'Wrong@'.$attempt])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['email' => 'owner@example.test', 'password' => self::ONE_TIME])->assertStatus(429);
    }

    public function test_email_reset_code_works_once_for_staff_and_reveals_nothing_for_unknown_emails(): void
    {
        Mail::fake();
        $this->bootstrap();

        $unknown = $this->postJson('/api/generate-otp', ['channel' => 'email', 'email' => 'nobody@example.test'])->assertOk()->json('message');
        Mail::assertNothingQueued();
        $known = $this->postJson('/api/generate-otp', ['channel' => 'email', 'email' => 'owner@example.test'])->assertOk()->json('message');
        $this->assertSame($unknown, $known);

        $code = null;
        Mail::assertQueued(PasswordResetCode::class, function (PasswordResetCode $mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->hasTo('owner@example.test');
        });
        $this->postJson('/api/reset-password', ['email' => 'owner@example.test', 'otp' => $code, 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertOk();
        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $this->assertFalse($admin->password_change_required);
        $this->assertTrue(Hash::check(self::STRONG, $admin->password));
        $this->postJson('/api/reset-password', ['email' => 'owner@example.test', 'otp' => $code, 'password' => 'Another-Pass-77!', 'password_confirmation' => 'Another-Pass-77!'])
            ->assertStatus(400);
    }

    public function test_customers_cannot_use_the_email_channel_or_the_staff_password_change(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'customer@example.test']);
        $this->postJson('/api/generate-otp', ['channel' => 'email', 'email' => 'customer@example.test'])->assertOk();
        Mail::assertNothingQueued();
        Sanctum::actingAs($customer);
        $this->postJson('/api/account/password', ['current_password' => 'password', 'password' => self::STRONG, 'password_confirmation' => self::STRONG])
            ->assertForbidden();
    }

    public function test_owner_reset_command_sets_a_one_time_password_and_ends_sessions(): void
    {
        $this->bootstrap();
        $admin = User::query()->where('email', 'owner@example.test')->firstOrFail();
        $admin->forceFill(['password_change_required' => false])->save();
        $admin->createToken('existing');

        $this->artisan('opfin:admin:reset-password', ['--email' => 'owner@example.test'])
            ->expectsQuestion('New one-time password (hidden)', 'Fresh@9753')->expectsQuestion('Type it again', 'Fresh@9753')->assertSuccessful();

        $admin->refresh();
        $this->assertTrue($admin->password_change_required);
        $this->assertSame(0, $admin->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.admin_password_reset_by_owner', 'subject_id' => $admin->id]);

        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'email' => 'customer@example.test']);
        $this->artisan('opfin:admin:reset-password', ['--email' => 'customer@example.test'])->assertFailed();
        $this->assertNotNull($customer->id);
    }

    public function test_users_no_longer_default_to_a_known_password(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'No credential', 'phone' => '256700009999', 'role' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_sms_reset_still_works_for_customers(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700000777']);
        Otp::query()->create(['phone' => '256700000777', 'otp' => Hash::make('482916'), 'attempts' => 0, 'expires_at' => now()->addMinutes(5)]);
        $this->postJson('/api/reset-password', ['phone' => '256700000777', 'otp' => '482916', 'pin' => '739164', 'pin_confirmation' => '739164'])->assertOk();
        $this->assertTrue(Hash::check('739164', $customer->fresh()->password));
    }

    private function bootstrap()
    {
        return $this->artisan('opfin:admin:bootstrap', ['--email' => 'owner@example.test', '--phone' => '+256 700 000123', '--first-name' => 'Test', '--last-name' => 'Owner'])
            ->expectsQuestion('One-time password (hidden)', self::ONE_TIME)
            ->expectsQuestion('Type it again', self::ONE_TIME);
    }
}
