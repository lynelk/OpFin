<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\IdentityVerificationReceipt;
use App\Models\KycCase;
use App\Models\User;
use App\Services\IdentityEvidenceRevocationService;
use App\Services\VerifiedIdentityEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityEvidenceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config([
            'identity_evidence.enabled' => true,
            'identity_evidence.policy_version' => 'test-policy',
            'identity_evidence.approval_reference' => 'synthetic-approval',
            'identity_evidence.max_age_seconds' => 86400,
            'identity_evidence.refresh_after_seconds' => 3600,
            'identity_evidence.retention_seconds' => 172800,
            'services.cito.environment' => 'SANDBOX',
            'services.cito.base_url' => 'https://identity.example.test',
            'services.cito.merchant_number' => 'test-merchant',
        ]);
        Http::preventStrayRequests();
        $user = User::factory()->create(['role' => 'customer']);
        $case = KycCase::create([
            'user_id' => $user->id, 'provider' => 'cito',
            'national_id' => 'CM123456789012', 'status' => KycCase::STATUS_VERIFIED,
            'submitted_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        $consent = ConsentRecord::create([
            'user_id' => $user->id, 'purpose' => 'identity_evidence_reuse',
            'policy_version' => 'test-consent', 'status' => 'granted',
            'channel' => 'test', 'granted_at' => now(),
        ]);
        app(VerifiedIdentityEvidenceService::class)->rememberNin($user, $case, [
            'status' => 'PASS', 'providerReference' => 'synthetic-original',
        ]);

        return [$user, $case, $consent];
    }

    public function test_definitive_revalidation_failure_removes_current_kyc_assurance(): void
    {
        [$user, $case] = $this->fixture();
        app(IdentityEvidenceRevocationService::class)->recordDefinitiveResult($user, $case, [
            'status' => 'FAIL', 'providerReference' => 'synthetic-new-rejection',
        ]);
        $case->refresh();
        $this->assertSame(KycCase::STATUS_PENDING_REVIEW, $case->status);
        $this->assertTrue($case->expires_at->lessThanOrEqualTo(now()));
        $this->assertSame('failed', $case->risk_flags['nin_revalidation']['status']);
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($user, $case));
        $this->assertDatabaseHas('audit_logs', ['event' => 'kyc.nin_revalidation_failed', 'actor_id' => $user->id]);
    }

    public function test_new_consent_does_not_reactivate_receipts_from_withdrawn_consent(): void
    {
        [$user, $case, $consent] = $this->fixture();
        $consent->update(['status' => 'revoked', 'revoked_at' => now()]);
        ConsentRecord::create([
            'user_id' => $user->id, 'purpose' => 'identity_evidence_reuse',
            'policy_version' => 'test-consent-v2', 'status' => 'granted',
            'channel' => 'test', 'granted_at' => now(),
        ]);
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($user, $case));
    }

    public function test_account_soft_deletion_purges_optional_cache_but_not_regulatory_case(): void
    {
        [$user, $case] = $this->fixture();
        $this->assertDatabaseCount('identity_verification_receipts', 1);
        $user->delete();
        $this->assertDatabaseCount('identity_verification_receipts', 0);
        $this->assertDatabaseHas('kyc_cases', ['id' => $case->id]);
    }

    public function test_disabled_refresh_does_not_call_a_provider_and_retention_cleanup_runs(): void
    {
        $this->fixture();
        config(['identity_evidence.enabled' => false]);
        $this->artisan('identity:evidence-maintain', ['--refresh' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->travel(3)->days();
        $this->artisan('identity:evidence-maintain', ['--limit' => 10])->assertSuccessful();
        $this->assertSame(0, IdentityVerificationReceipt::count());
    }
}
