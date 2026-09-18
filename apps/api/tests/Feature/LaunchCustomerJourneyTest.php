<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\CreditProfile;
use App\Models\CreditScoreComponent;
use App\Models\KycCase;
use App\Models\Otp;
use App\Models\User;
use App\Services\CustomerCreditProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LaunchCustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_first_registration_creates_pin_session_and_primary_wallet(): void
    {
        $phone = '256700100001';
        $verificationToken = str_repeat('a', 64);

        Otp::create([
            'phone' => $phone,
            'otp' => Hash::make('456321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
        ]);

        $response = $this->postJson('/api/register', [
            'phone' => $phone,
            'verification_token' => $verificationToken,
            'first_name' => 'Amina',
            'other_name' => 'N',
            'last_name' => 'Kato',
            'pin' => '482951',
            'pin_confirmation' => '482951',
            'terms_accepted' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.first_name', 'Amina')
            ->assertJsonPath('data.user.last_name', 'Kato')
            ->assertJsonPath('data.credit_profile.setup.secondary_phone_required', false)
            ->assertJsonStructure(['data' => ['access_token']]);

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $this->assertTrue(Hash::check('482951', $user->password));
        $this->assertDatabaseHas('customer_phone_numbers', [
            'user_id' => $user->id,
            'phone' => $phone,
            'kind' => 'primary',
        ]);
        $this->assertDatabaseHas('customer_wallets', [
            'user_id' => $user->id,
            'msisdn' => $phone,
            'is_default_disbursement' => true,
            'is_default_repayment' => true,
        ]);
    }

    public function test_pin_login_works_and_predictable_pin_is_rejected(): void
    {
        $user = User::factory()->create([
            'phone' => '256700100002',
            'password' => Hash::make('482951'),
        ]);

        $this->postJson('/api/login', [
            'phone' => $user->phone,
            'pin' => '482951',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token']]);

        $phone = '256700100003';
        $token = str_repeat('b', 64);
        Otp::create([
            'phone' => $phone,
            'otp' => Hash::make('654987'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $token),
        ]);

        $this->postJson('/api/register', [
            'phone' => $phone,
            'verification_token' => $token,
            'first_name' => 'John',
            'last_name' => 'Okello',
            'pin' => '123456',
            'pin_confirmation' => '123456',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_kyc_requires_front_back_and_selfie_with_id(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->post('/api/kyc/cases', [
            'national_id' => 'CM123456789012',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([
                'national_id_front',
                'national_id_back',
                'selfie_with_id',
            ]);
    }

    public function test_second_phone_is_optional_for_profile_scoring(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone' => '256700100004',
            'phone_verified_at' => now(),
        ]);
        $this->verifiedIdentityAndConsent($user);

        CrbReport::create([
            'user_id' => $user->id,
            'provider' => 'test-crb',
            'status' => CrbReport::STATUS_CLEAR,
            'score' => 80,
            'requested_at' => now(),
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        CreditScoreComponent::create([
            'user_id' => $user->id,
            'source' => 'crb',
            'status' => CreditScoreComponent::STATUS_READY,
            'score' => 80,
            'weight_percent' => 40,
            'reason_codes' => ['CRB_CLEAR'],
            'raw_payload' => [],
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $profile = app(CustomerCreditProfileService::class)
            ->refresh($user->fresh(), false);

        $this->assertGreaterThan(0, $profile->credit_limit_minor);
        $this->assertGreaterThan(0, $profile->available_to_borrow_minor);
        $this->assertSame(60.0, (float) $profile->coverage_percent);

        Sanctum::actingAs($user);
        $this->getJson('/api/credit/profile')
            ->assertOk()
            ->assertJsonPath('data.setup.secondary_phone_required', false)
            ->assertJsonPath('data.setup.secondary_phone_verified', false);
    }

    public function test_application_cannot_exceed_available_profile_limit(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        $this->verifiedIdentityAndConsent($user);

        CreditProfile::create([
            'user_id' => $user->id,
            'status' => CreditProfile::STATUS_READY,
            'composite_score' => 70,
            'band' => 'Good',
            'coverage_percent' => 100,
            'credit_limit_minor' => 100000,
            'current_exposure_minor' => 0,
            'available_to_borrow_minor' => 100000,
            'amount_due_minor' => 0,
            'total_outstanding_minor' => 0,
            'model_version' => 'test',
            'component_breakdown' => [],
            'reason_codes' => [],
            'customer_explanations' => [],
            'scored_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/credit/applications', [
            'amount_minor' => 150000,
            'reason' => 'Education',
            'distribution_channel' => 'play_store',
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.amount_minor.0',
                'AMOUNT_EXCEEDS_AVAILABLE_LIMIT'
            );
    }

    public function test_ussd_uses_same_simple_customer_state(): void
    {
        User::factory()->create([
            'phone' => '256700100005',
            'phone_verified_at' => now(),
        ]);

        $this->post('/api/ussd', [
            'sessionId' => 'session-1',
            'phoneNumber' => '256700100005',
            'text' => '',
        ])->assertOk()
            ->assertSee('CON OpFin')
            ->assertSee('1. My limit')
            ->assertSee('5. Complete profile');
    }

    private function verifiedIdentityAndConsent(User $user): void
    {
        KycCase::create([
            'user_id' => $user->id,
            'provider' => 'test',
            'national_id' => 'CM'.str_pad((string) $user->id, 12, '0', STR_PAD_LEFT),
            'status' => KycCase::STATUS_VERIFIED,
            'liveness_status' => 'valid',
            'face_match_status' => 'valid',
            'nin_phone_link_status' => 'valid',
            'evidence_complete_at' => now(),
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHour(),
            'expires_at' => now()->addYear(),
        ]);

        ConsentRecord::create([
            'user_id' => $user->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-consent-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'test',
            'granted_at' => now(),
        ]);
    }
}
