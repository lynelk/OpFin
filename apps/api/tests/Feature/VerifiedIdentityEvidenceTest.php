<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\IdentityVerificationReceipt;
use App\Models\KycCase;
use App\Models\User;
use App\Services\CitoCapabilityClient;
use App\Services\VerifiedIdentityEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class VerifiedIdentityEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private User $subject;

    private KycCase $case;

    private ConsentRecord $consent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 25)->startOfDay());
        config([
            'identity_evidence.enabled' => true,
            'identity_evidence.policy_version' => 'synthetic-test-v1',
            'identity_evidence.approval_reference' => 'synthetic-policy-only',
            'identity_evidence.max_age_seconds' => 86400,
            'identity_evidence.refresh_after_seconds' => 3600,
            'identity_evidence.retention_seconds' => 172800,
            'services.cito.base_url' => 'https://provider.example.test',
            'services.cito.merchant_number' => 'synthetic-merchant',
            'services.cito.environment' => 'SANDBOX',
        ]);
        Http::preventStrayRequests();
        $this->subject = User::factory()->create(['role' => 'customer']);
        $this->case = KycCase::create([
            'user_id' => $this->subject->id, 'provider' => 'cito',
            'national_id' => 'CM123456789012', 'status' => KycCase::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
        ]);
        $this->consent = ConsentRecord::create([
            'user_id' => $this->subject->id, 'purpose' => 'identity_evidence_reuse',
            'policy_version' => 'synthetic-consent-v1', 'status' => 'granted',
            'channel' => 'test', 'granted_at' => now(),
        ]);
    }

    private function remember(string $status = 'PASS', string $reference = 'synthetic-provider-001'): void
    {
        app(VerifiedIdentityEvidenceService::class)->rememberNin($this->subject, $this->case, [
            'status' => $status, 'providerReference' => $reference,
            'reference' => 'synthetic-gateway-001', 'access_token' => 'must-not-be-stored',
            'nin' => 'CM123456789012', 'fullName' => 'must-not-be-stored',
        ]);
    }

    public function test_fresh_evidence_avoids_an_external_nin_call_without_completing_kyc(): void
    {
        $this->remember();
        $result = app(CitoCapabilityClient::class)->identityCheck($this->subject, $this->case, 'NIN');
        $this->assertSame('PASS', $result['status']);
        $this->assertSame('internal_verified_evidence', $result['evidenceSource']);
        $this->assertSame(KycCase::STATUS_PENDING_REVIEW, $this->case->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_reuse_and_replay_do_not_refresh_the_original_observation_time(): void
    {
        $this->remember();
        $before = IdentityVerificationReceipt::firstOrFail()->verified_at->toIso8601String();
        $this->travel(2)->hours();
        $this->remember();
        $result = app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case);
        $this->assertSame($before, $result['verifiedAt']);
        $this->assertTrue($result['revalidationDue']);
        $this->assertDatabaseCount('identity_verification_receipts', 1);
    }

    public function test_expiry_is_not_silently_extended_during_an_outage(): void
    {
        $this->remember();
        $this->travel(1)->days();
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case));
    }

    public function test_revoked_consent_prevents_reuse(): void
    {
        $this->remember();
        $this->consent->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case));
    }

    public function test_a_new_definitive_failure_supersedes_a_previous_pass(): void
    {
        $this->remember();
        $this->remember('FAIL', 'synthetic-provider-002');
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case));
    }

    public function test_identity_detail_changes_require_new_evidence(): void
    {
        $this->remember();
        $this->subject->first_name = 'Changed';
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case));
    }

    public function test_sandbox_evidence_cannot_be_reused_in_live_context(): void
    {
        $this->remember();
        config(['services.cito.environment' => 'LIVE']);
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($this->subject, $this->case));
    }

    public function test_another_subject_cannot_read_or_use_a_receipt(): void
    {
        $this->remember();
        $other = User::factory()->create(['role' => 'customer']);
        $this->expectException(InvalidArgumentException::class);
        app(VerifiedIdentityEvidenceService::class)->reusableNin($other, $this->case);
    }

    public function test_missing_policy_or_attribution_does_not_create_reusable_evidence(): void
    {
        config(['identity_evidence.approval_reference' => '']);
        $this->remember();
        $this->assertDatabaseCount('identity_verification_receipts', 0);
        config(['identity_evidence.approval_reference' => 'synthetic-policy-only']);
        $this->remember('PASS', '');
        $this->assertDatabaseCount('identity_verification_receipts', 0);
    }

    public function test_raw_identity_fields_and_tokens_are_not_persisted_in_the_cache(): void
    {
        $this->remember();
        $row = (array) DB::table('identity_verification_receipts')->first();
        $stored = json_encode($row, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('CM123456789012', $stored);
        $this->assertStringNotContainsString('must-not-be-stored', $stored);
        $this->assertStringNotContainsString('synthetic-provider-001', $stored);
        $receipt = IdentityVerificationReceipt::firstOrFail();
        $this->assertArrayNotHasKey('access_token', $receipt->result);
        $this->assertArrayNotHasKey('nin', $receipt->result);
        $this->assertSame('synthetic-provider-001', $receipt->provider_reference);
    }

    public function test_explicit_invalidation_and_retention_cleanup_do_not_delete_the_customer(): void
    {
        $this->remember();
        $service = app(VerifiedIdentityEvidenceService::class);
        $this->assertSame(1, $service->invalidateForUser($this->subject));
        $this->assertNull($service->reusableNin($this->subject, $this->case));
        $this->travel(3)->days();
        $this->assertSame(1, $service->purgeExpiredRetention());
        $this->assertDatabaseHas('users', ['id' => $this->subject->id]);
    }
}
