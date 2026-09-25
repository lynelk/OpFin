<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ConsentRecord;
use App\Models\IdentityVerificationReceipt;
use App\Models\KycCase;
use App\Models\User;
use App\Services\CitoCapabilityClient;
use App\Services\IdentityEvidenceRevocationService;
use App\Services\VerifiedIdentityEvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentityEvidenceReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config([
            'identity_evidence.enabled' => true,
            'identity_evidence.policy_version' => 'review-test',
            'identity_evidence.approval_reference' => 'synthetic-review',
            'identity_evidence.max_age_seconds' => 86400,
            'identity_evidence.refresh_after_seconds' => 3600,
            'identity_evidence.retention_seconds' => 172800,
            'services.cito.environment' => 'SANDBOX',
            'services.cito.base_url' => 'https://identity.example.test',
            'services.cito.merchant_number' => 'synthetic-merchant',
        ]);
        Http::preventStrayRequests();
        $user = User::factory()->create(['role' => 'customer']);
        $case = KycCase::create([
            'user_id' => $user->id, 'provider' => 'cito', 'national_id' => 'CM123456789012',
            'status' => KycCase::STATUS_VERIFIED, 'submitted_at' => now(), 'expires_at' => now()->addYear(),
        ]);
        ConsentRecord::create([
            'user_id' => $user->id, 'purpose' => 'identity_evidence_reuse',
            'policy_version' => 'synthetic-consent', 'status' => 'granted', 'channel' => 'test', 'granted_at' => now(),
        ]);
        app(VerifiedIdentityEvidenceService::class)->rememberNin($user, $case, [
            'status' => 'PASS', 'providerReference' => 'synthetic-original',
        ]);

        return [$user, $case];
    }

    public function test_an_unreferenced_failure_cannot_leave_an_old_pass_reusable(): void
    {
        [$user, $case] = $this->fixture();
        app(IdentityEvidenceRevocationService::class)->recordDefinitiveResult($user, $case, ['status' => 'FAIL']);
        $this->assertDatabaseCount('identity_verification_receipts', 1);
        $this->assertNotNull(IdentityVerificationReceipt::firstOrFail()->revoked_at);
        $this->assertNull(app(VerifiedIdentityEvidenceService::class)->reusableNin($user, $case->fresh()));
        $this->assertSame(KycCase::STATUS_PENDING_REVIEW, $case->fresh()->status);
    }

    public function test_obsolete_receipt_is_retired_without_refreshing_a_different_nin(): void
    {
        [$user] = $this->fixture();
        $this->travel(2)->hours();
        KycCase::create([
            'user_id' => $user->id, 'provider' => 'cito', 'national_id' => 'CF123456789012',
            'status' => KycCase::STATUS_PENDING_REVIEW, 'submitted_at' => now(),
        ]);
        $this->mock(CitoCapabilityClient::class, function ($mock): void {
            $mock->shouldReceive('configured')->once()->andReturn(true);
            $mock->shouldNotReceive('identityCheck');
        });
        $this->artisan('identity:evidence-maintain', ['--refresh' => true])->assertSuccessful();
        $this->assertNotNull(IdentityVerificationReceipt::firstOrFail()->revoked_at);
        Http::assertNothingSent();
    }

    public function test_automated_refresh_audit_records_the_system_not_the_customer_as_actor(): void
    {
        [$user, $case] = $this->fixture();
        $service = app(IdentityEvidenceRevocationService::class);
        $service->recordDefinitiveResult($user, $case, [
            'status' => 'PASS', 'providerReference' => 'synthetic-scheduled-pass',
        ], systemInitiated: true);
        $record = AuditLog::where('event', 'identity.evidence.recorded')->latest('id')->firstOrFail();
        $this->assertNull($record->actor_id);
        $this->assertSame('scheduled_revalidation', $record->metadata['trigger']);
        $service->recordDefinitiveResult($user, $case, ['status' => 'FAIL'], systemInitiated: true);
        $failure = AuditLog::where('event', 'kyc.nin_revalidation_failed')->latest('id')->firstOrFail();
        $this->assertNull($failure->actor_id);
        $this->assertEquals($case->id, $failure->subject_id);
        $this->assertSame('scheduled_revalidation', $failure->metadata['trigger']);
    }
}
