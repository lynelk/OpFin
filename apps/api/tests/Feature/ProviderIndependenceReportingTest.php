<?php

namespace Tests\Feature;

use App\Models\CapitalMandate;
use App\Models\ConsentRecord;
use App\Models\CreditOffer;
use App\Models\KycCase;
use App\Models\User;
use App\Services\FundingPoolService;
use App\Services\IdentityVerificationService;
use App\Services\PositiveEmploymentBehaviourService;
use App\Services\ServiceEconomicsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderIndependenceReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_employer_behaviour_is_neutral_and_verified_positive_signal_only_adds_benefit(): void
    {
        $user = User::factory()->create();

        $neutral = app(PositiveEmploymentBehaviourService::class)->assess($user);
        $this->assertSame(0.0, $neutral['uplift_points']);
        $this->assertSame('neutral', $neutral['absence_effect']);
        $this->assertSame('neutral', $neutral['negative_signal_effect']);

        $consent = ConsentRecord::create([
            'user_id' => $user->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'credit-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'api',
            'granted_at' => now(),
        ]);

        DB::table('alternative_data_signals')->insert([
            'user_id' => $user->id,
            'source_type' => 'employer',
            'signal_key' => 'positive_performance',
            'signal_value' => json_encode(true, JSON_THROW_ON_ERROR),
            'purpose' => 'credit_assessment',
            'consent_record_id' => $consent->id,
            'risk_eligible' => true,
            'verified' => true,
            'provider_reference' => 'EMP-POSITIVE-001',
            'provenance' => json_encode(['source' => 'employer_verified'], JSON_THROW_ON_ERROR),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('alternative_data_signals')->insert([
            'user_id' => $user->id,
            'source_type' => 'employer',
            'signal_key' => 'attendance_reliability',
            'signal_value' => json_encode(false, JSON_THROW_ON_ERROR),
            'purpose' => 'credit_assessment',
            'consent_record_id' => $consent->id,
            'risk_eligible' => true,
            'verified' => true,
            'provider_reference' => 'EMP-NEGATIVE-001',
            'provenance' => json_encode(['source' => 'employer_verified'], JSON_THROW_ON_ERROR),
            'observed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $benefit = app(PositiveEmploymentBehaviourService::class)->assess($user);

        $this->assertSame(1.5, $benefit['uplift_points']);
        $this->assertSame(['POSITIVE_EMPLOYMENT_BEHAVIOUR_UPLIFT'], $benefit['reason_codes']);
        $this->assertCount(1, $benefit['applied_signals']);
        $this->assertSame('positive_performance', $benefit['applied_signals'][0]['signal_key']);

        $consent->update([
            'status' => ConsentRecord::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $afterRevocation = app(PositiveEmploymentBehaviourService::class)->assess($user);
        $this->assertSame(0.0, $afterRevocation['uplift_points']);
        $this->assertSame([], $afterRevocation['applied_signals']);
    }

    public function test_cito_is_primary_for_nin_and_phone_ownership_while_biometrics_remain_pending_when_not_configured(): void
    {
        $this->configureCito();

        $user = User::factory()->create([
            'phone' => '256700123456',
            'phone_verified_at' => now(),
            'first_name' => 'Amina',
            'last_name' => 'Kato',
        ]);
        $case = KycCase::create([
            'user_id' => $user->id,
            'provider' => 'pending',
            'national_id' => 'CM123456789012',
            'status' => KycCase::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
            'evidence_complete_at' => now(),
            'evidence' => ['identity_verification_requested' => true],
            'risk_flags' => ['CITO_IDENTITY_PROVIDER_ERROR', 'UNRELATED_MANUAL_REVIEW_FLAG'],
        ]);

        config([
            'opfin.integrations.external_service_route' => 'auto',
            'services.identity_verification.url' => '',
        ]);

        Http::fake([
            'https://cito.test/api/v2/identity/nin/verifications' => Http::response([
                'reference' => 'CAP-NIN-001',
                'capability' => 'NIN',
                'status' => 'PASS',
                'reasonCodes' => ['NIN_MATCH'],
                'providerReference' => 'GNUGRID-NIN-001',
            ], 200),
            'https://cito.test/api/v2/identity/verifications' => Http::response([
                'reference' => 'CAP-PHONE-001',
                'capability' => 'PHONE_OWNERSHIP',
                'status' => 'PASS',
                'reasonCodes' => ['PHONE_OWNERSHIP_MATCHED'],
                'providerReference' => 'GNUGRID-PHONE-001',
            ], 200),
        ]);

        $verified = app(IdentityVerificationService::class)->verify($case);

        $this->assertSame('cito', $verified->provider);
        $this->assertSame('GNUGRID-NIN-001', $verified->provider_reference);
        $this->assertSame('valid', $verified->nin_phone_link_status);
        $this->assertSame('pending_review', $verified->liveness_status);
        $this->assertSame('pending_review', $verified->face_match_status);
        $this->assertSame(KycCase::STATUS_PENDING_REVIEW, $verified->status);
        $this->assertContains('BIOMETRIC_PROVIDER_NOT_CONFIGURED', $verified->risk_flags);
        $this->assertNotContains('CITO_IDENTITY_PROVIDER_ERROR', $verified->risk_flags);
        $this->assertContains('UNRELATED_MANUAL_REVIEW_FLAG', $verified->risk_flags);
        $this->assertSame('CITO_MANAGED', $verified->evidence['identity_route']);
        $this->assertSame('GNUGRID-PHONE-001', $verified->evidence['phone_provider_reference']);

        $this->assertDatabaseHas('service_economics_events', [
            'service_code' => 'identity',
            'capability_code' => 'NIN',
            'route' => 'CITO_MANAGED',
            'status' => 'PASS',
        ]);
        $this->assertDatabaseHas('service_economics_events', [
            'service_code' => 'identity',
            'capability_code' => 'PHONE_OWNERSHIP',
            'route' => 'CITO_MANAGED',
            'status' => 'PASS',
        ]);

        Http::assertSent(function ($request) use ($case) {
            $json = $request->data();

            return str_contains($request->url(), '/api/v2/identity/')
                && data_get($json, 'consent.purpose') === 'identity_verification'
                && data_get($json, 'consent.reference') === 'kyc-case:'.$case->id;
        });
    }

    public function test_ambiguous_cito_identity_failure_does_not_silently_call_direct_provider(): void
    {
        $this->configureCito();

        $user = User::factory()->create([
            'phone' => '256700654321',
            'phone_verified_at' => now(),
        ]);
        $case = KycCase::create([
            'user_id' => $user->id,
            'provider' => 'pending',
            'national_id' => 'CM123456789013',
            'status' => KycCase::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
            'evidence_complete_at' => now(),
            'evidence' => ['identity_verification_requested' => true],
        ]);

        config([
            'opfin.integrations.external_service_route' => 'auto',
            'services.identity_verification.url' => 'https://direct-id.test/verify',
        ]);

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://cito.test/')) {
                return Http::response(['code' => 'PROVIDER_UNAVAILABLE'], 503);
            }

            return Http::response([
                'nin_valid' => true,
                'liveness_valid' => true,
                'face_match_valid' => true,
                'nin_phone_link_valid' => true,
                'reference' => 'DIRECT-SHOULD-NOT-RUN',
            ], 200);
        });

        $result = app(IdentityVerificationService::class)->verify($case);

        $this->assertSame('cito', $result->provider);
        $this->assertSame(KycCase::STATUS_PENDING_REVIEW, $result->status);
        $this->assertContains('CITO_IDENTITY_PROVIDER_ERROR', $result->risk_flags);
        $this->assertContains('DIRECT_RETRY_REQUIRES_EXPLICIT_ROUTE_SWITCH', $result->risk_flags);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://direct-id.test/'));

        $this->assertDatabaseHas('service_economics_events', [
            'service_code' => 'identity',
            'capability_code' => 'NIN',
            'route' => 'CITO_MANAGED',
            'status' => 'ERROR',
        ]);
    }

    public function test_service_economics_reconciliation_is_idempotent_and_preserves_known_values(): void
    {
        $service = app(ServiceEconomicsService::class);

        $first = $service->record([
            'service_code' => 'insurance',
            'capability_code' => 'premium_collection_and_settlement',
            'provider' => 'Example Insurer',
            'route' => 'DIRECT_PARTNER',
            'request_reference' => 'OPF-PRM-ECON-001',
            'status' => 'COLLECTED',
            'currency' => 'UGX',
            'provider_gross_cost_minor' => 100,
            'provider_discount_minor' => 0,
            'customer_service_charge_minor' => 0,
            'customer_platform_fee_minor' => 50,
            'partner_commission_minor' => 0,
            'cito_platform_fee_minor' => 0,
            'opfin_platform_fee_minor' => 0,
            'tax_amount_minor' => 0,
        ]);

        $this->assertNull($first->gross_revenue_minor);
        $this->assertNull($first->net_revenue_minor);
        $this->assertNull($first->gross_margin_minor);

        $second = $service->record([
            'service_code' => 'insurance',
            'provider' => 'Example Insurer',
            'route' => 'DIRECT_PARTNER',
            'request_reference' => 'OPF-PRM-ECON-001',
            'status' => 'RECONCILED',
            'provider_reference' => 'INS-SETTLEMENT-001',
            'net_settlement_to_provider_minor' => 100,
            'gross_revenue_minor' => 50,
            'net_revenue_minor' => 50,
            'gross_margin_minor' => -50,
            'reconciled_at' => now(),
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(50, (int) $second->customer_platform_fee_minor);
        $this->assertSame(100, (int) $second->net_settlement_to_provider_minor);
        $this->assertSame(50, (int) $second->gross_revenue_minor);
        $this->assertSame(50, (int) $second->net_revenue_minor);
        $this->assertSame(-50, (int) $second->gross_margin_minor);
        $this->assertSame('RECONCILED', $second->status);
        $this->assertDatabaseCount('service_economics_events', 1);
    }    
    public function test_funding_pool_reservation_deployment_release_and_reversal_are_idempotent(): void
    {
        $user = User::factory()->create();
        $offer = $this->creditOfferWithFundingPool($user, 500, 1500);
        $pool = CapitalMandate::findOrFail($offer->funding_pool_id);
        $service = app(FundingPoolService::class);

        $service->reserve($offer);
        $service->reserve($offer->fresh());

        $pool->refresh();
        $this->assertSame(500, (int) $pool->reserved_capital_minor);
        $this->assertSame(0, (int) $pool->deployed_capital_minor);

        $service->commit($offer->fresh());
        $service->commit($offer->fresh());

        $pool->refresh();
        $this->assertSame(0, (int) $pool->reserved_capital_minor);
        $this->assertSame(500, (int) $pool->deployed_capital_minor);

        $service->reverseCommitted($offer->fresh());
        $service->reverseCommitted($offer->fresh());

        $pool->refresh();
        $this->assertSame(0, (int) $pool->reserved_capital_minor);
        $this->assertSame(0, (int) $pool->deployed_capital_minor);

        $releaseOffer = $this->creditOfferWithFundingPool($user, 300, 1000);
        $releasePool = CapitalMandate::findOrFail($releaseOffer->funding_pool_id);

        $service->reserve($releaseOffer);
        $service->release($releaseOffer->fresh());
        $service->release($releaseOffer->fresh());

        $releasePool->refresh();
        $this->assertSame(0, (int) $releasePool->reserved_capital_minor);
        $this->assertSame(0, (int) $releasePool->deployed_capital_minor);
    }

    private function configureCito(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);
        $exported = openssl_pkey_export($resource, $privateKey);
        $this->assertTrue($exported);

        config([
            'services.cito.base_url' => 'https://cito.test',
            'services.cito.merchant_number' => 'OPFIN-TEST',
            'services.cito.private_key' => $privateKey,
            'services.cito.environment' => 'SANDBOX',
        ]);
    }

    private function creditOfferWithFundingPool(User $user, int $principalMinor, int $committedMinor): CreditOffer
    {
        $now = now();
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution '.Str::random(6),
            'address' => 'Kampala',
            'phone' => '256700000001',
            'email' => Str::lower(Str::random(8)).'@example.test',
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $loanProductId = DB::table('loan_products')->insertGetId([
            'name' => 'Test Product '.Str::random(6),
            'type' => 'Cash',
            'status' => 'Active',
            'institution_id' => (string) $institutionId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $termId = DB::table('loan_product_terms')->insertGetId([
            'loan_product_id' => $loanProductId,
            'interest_rate' => 2.8,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 30,
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $applicationId = DB::table('loan_applications')->insertGetId([
            'user_id' => $user->id,
            'loan_product_id' => $loanProductId,
            'loan_product_term_id' => $termId,
            'institution_id' => $institutionId,
            'amount' => (string) $principalMinor,
            'status' => 'Approved',
            'reason' => 'test',
            'distribution_channel' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $decisionId = DB::table('credit_decisions')->insertGetId([
            'loan_application_id' => $applicationId,
            'user_id' => $user->id,
            'status' => 'approved',
            'requested_amount_minor' => $principalMinor,
            'approved_amount_minor' => $principalMinor,
            'reason_codes' => json_encode(['TEST_APPROVAL'], JSON_THROW_ON_ERROR),
            'decision_summary' => 'Test approval',
            'decided_at' => $now,
            'policy_version' => 'test-v1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $pool = CapitalMandate::create([
            'reference' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'mandate_type' => 'private_loan_book',
            'name' => 'Test Funding Pool '.Str::random(6),
            'committed_capital_minor' => $committedMinor,
            'deployed_capital_minor' => 0,
            'reserved_capital_minor' => 0,
            'status' => 'active',
            'investment_policy' => ['test' => true],
            'approved_by' => $user->id,
            'approved_at' => $now,
        ]);

        return CreditOffer::create([
            'loan_application_id' => $applicationId,
            'credit_decision_id' => $decisionId,
            'user_id' => $user->id,
            'institution_id' => $institutionId,
            'funding_pool_id' => $pool->id,
            'created_by' => $user->id,
            'offer_reference' => 'OPF-OFR-'.Str::upper(Str::random(16)),
            'version' => 1,
            'status' => CreditOffer::STATUS_OFFERED,
            'currency' => 'UGX',
            'principal_amount_minor' => $principalMinor,
            'interest_amount_minor' => 0,
            'fees_minor' => 0,
            'net_disbursement_minor' => $principalMinor,
            'total_repayment_minor' => $principalMinor,
            'duration_days' => 30,
            'interest_rate_percent' => 0,
            'interest_cycle' => 'Monthly',
            'interest_type' => 'Flat',
            'repayment_frequency' => 'Monthly',
            'fee_treatment' => 'financed',
            'policy_version' => 'test-v1',
            'pricing_snapshot' => ['test' => true],
            'disclosure_snapshot' => ['test' => true],
            'offered_at' => $now,
            'expires_at' => $now->copy()->addHour(),
        ]);
    }
}
