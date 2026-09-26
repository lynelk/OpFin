<?php

namespace Tests\Feature;

use App\Contracts\MobileMoneyProviderInterface;
use App\Contracts\MobileMoneyProviderReadinessInterface;
use App\Models\CreditDecision;
use App\Models\CreditScoreComponent;
use App\Models\CustomerWallet;
use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\CreditProductAvailabilityService;
use App\Services\FinancialIntegrityService;
use App\Services\FinancialReadinessService;
use App\Services\MobileMoney\MobileMoneyProviderManager;
use App\Services\MobileMoney\MobileMoneyProviderResponse;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\ProductionCreditOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class FinancialTestAdapterBase implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::pending('financial-test', 'test-'.$transaction->internal_reference);
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::pending('financial-test', 'test-'.$transaction->internal_reference);
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::pending('financial-test', $transaction->provider_reference ?: 'test-'.$transaction->internal_reference);
    }

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::pending('financial-test', $payload['reference'] ?? 'test-webhook');
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return new MobileMoneyProviderResponse(
            provider: 'financial-test',
            successful: true,
            status: MobileMoneyTransaction::STATUS_REVERSED,
            providerReference: $transaction->provider_reference,
            reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
        );
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('financial-test', $reason);
    }
}

class ReadyFinancialTestAdapter extends FinancialTestAdapterBase implements MobileMoneyProviderReadinessInterface
{
    public function readiness(): array
    {
        return ['status' => 'ready', 'missing' => []];
    }
}

class AmbiguousFinancialTestAdapter extends ReadyFinancialTestAdapter
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        throw new RuntimeException('Synthetic timeout after provider submission.');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        throw new RuntimeException('Synthetic timeout after provider submission.');
    }
}

class FinancialCompletionControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPricingPolicy();
    }

    public function test_inactive_products_and_terms_fail_closed_at_authoritative_availability_service(): void
    {
        [$institution, , , $product, $term, $application] = $this->creditFixture(false);
        $availability = app(CreditProductAvailabilityService::class);

        $availability->assertAvailable($product, $term, $institution->id);

        $product->update(['status' => 'Inactive']);
        try {
            $availability->assertApplicationAvailable($application->fresh());
            $this->fail('An inactive credit product must not remain offerable.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('product is not active', $exception->getMessage());
        }

        $product->update(['status' => 'Active']);
        $term->update(['status' => 'Inactive']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('term is not active');
        $availability->assertApplicationAvailable($application->fresh());
    }

    public function test_invalid_or_missing_disbursement_wallet_cannot_mutate_offer_or_reserve_funding(): void
    {
        [, $customer, $operations, , , $application] = $this->creditFixture(false);
        $offer = $this->createOffer($application, $operations);
        $poolId = (int) $offer->funding_pool_id;

        $other = User::factory()->create();
        $foreignWallet = CustomerWallet::create([
            'user_id' => $other->id,
            'provider' => 'mock',
            'msisdn' => '256700009999',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        try {
            app(ProductionCreditOfferService::class)->acceptOffer($offer, $customer, [
                'wallet_id' => $foreignWallet->id,
                'channel' => 'regression',
            ]);
            $this->fail('Another customer wallet must be rejected before any financial state mutation.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('verified disbursement wallet', $exception->getMessage());
        }

        $this->assertSame('offered', strtolower((string) $offer->fresh()->status));
        $this->assertNull($offer->fresh()->accepted_at);
        $this->assertNull($offer->fresh()->funding_reserved_at);
        $this->assertSame(0, (int) DB::table('capital_mandates')->where('id', $poolId)->value('reserved_capital_minor'));
        $this->assertDatabaseMissing('mobile_money_transactions', ['credit_offer_id' => $offer->id]);

        try {
            app(ProductionCreditOfferService::class)->acceptOffer($offer->fresh(), $customer, []);
            $this->fail('Credit disbursement must require a verified customer wallet.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('verified disbursement wallet is required', $exception->getMessage());
        }

        $this->assertSame('offered', strtolower((string) $offer->fresh()->status));
        $this->assertSame(0, (int) DB::table('capital_mandates')->where('id', $poolId)->value('reserved_capital_minor'));
    }

    public function test_provider_configuration_failure_preserves_durable_failed_intent(): void
    {
        config([
            'services.cpay.base_url' => null,
            'services.cpay.merchant_number' => null,
            'services.cpay.private_key' => null,
            'services.cpay.callback_url' => null,
        ]);

        try {
            app(MobileMoneyService::class)->collect([
                'amount_minor' => 10000,
                'currency' => 'UGX',
                'phone' => '256700008888',
                'idempotency_key' => 'durable-money-intent-001',
                'internal_reference' => 'OPF-DURABLE-001',
                'purpose' => 'regression_test',
            ], 'cpay');
            $this->fail('Missing provider configuration must fail closed.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('not configured', $exception->getMessage());
        }

        $intent = MobileMoneyTransaction::query()->where('idempotency_key', 'durable-money-intent-001')->firstOrFail();
        $this->assertSame(MobileMoneyTransaction::STATUS_FAILED, $intent->status);
        $this->assertSame(MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED, $intent->accounting_status);
        $this->assertSame('rejected_before_provider_finality', $intent->provider_submission_state);
        $this->assertSame('OPF-DURABLE-001', $intent->internal_reference);
    }

    public function test_ambiguous_provider_submission_keeps_offer_pending_and_capital_reserved_until_reconciliation(): void
    {
        config([
            'services.mobile_money.default_provider' => 'ambiguous_test',
            'services.mobile_money.providers.ambiguous_test.adapter' => AmbiguousFinancialTestAdapter::class,
            'services.mobile_money.providers.ambiguous_test.production_certified' => true,
        ]);

        [, $customer, $operations, , , $application] = $this->creditFixture(true);
        $offer = $this->createOffer($application, $operations);
        $poolId = (int) $offer->funding_pool_id;

        $accepted = app(ProductionCreditOfferService::class)->acceptOffer($offer, $customer, []);
        $movement = $accepted['mobile_money']->fresh();

        $this->assertSame(CreditOfferStatus::DISBURSEMENT_PENDING, $accepted['offer']->fresh()->status);
        $this->assertSame('ambiguous', $movement->provider_submission_state);
        $this->assertSame(MobileMoneyTransaction::STATUS_PROCESSING, $movement->status);
        $this->assertSame((int) $offer->principal_amount_minor, (int) DB::table('capital_mandates')->where('id', $poolId)->value('reserved_capital_minor'));
        $this->assertNull($offer->fresh()->funding_released_at);

        app(ProductionCreditOfferService::class)->acceptOffer($offer->fresh(), $customer, []);
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertSame('ambiguous', $movement->fresh()->provider_submission_state);
    }

    public function test_direct_provider_requires_explicit_readiness_contract_even_when_certified(): void
    {
        config([
            'services.mobile_money.providers.no_readiness.adapter' => FinancialTestAdapterBase::class,
            'services.mobile_money.providers.no_readiness.production_certified' => true,
        ]);

        $status = app(MobileMoneyProviderManager::class)->readiness('no_readiness');

        $this->assertSame('blocked', $status['status']);
        $this->assertContains('provider_readiness_contract', $status['missing']);
    }

    public function test_reconciliation_matching_is_evidence_only_and_write_off_is_maker_checker_with_summary_refresh(): void
    {
        $maker = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $support = User::factory()->create(['role' => User::ROLE_SUPPORT]);

        $run = ReconciliationRun::create([
            'provider' => 'cpay',
            'business_date' => now()->toDateString(),
            'status' => ReconciliationRun::STATUS_OPEN,
            'created_by' => $maker->id,
            'started_at' => now(),
            'summary' => ['exception_count' => 1, 'written_off_count' => 0],
        ]);
        $money = MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 20000,
            'currency' => 'UGX',
            'phone' => '256700007777',
            'idempotency_key' => 'reconciliation-control-001',
            'internal_reference' => 'OPF-REC-001',
            'provider_reference' => 'CPAY-REC-001',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
            'statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_EXCEPTION,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
            'metadata' => [],
        ]);
        $item = ReconciliationItem::create([
            'reconciliation_run_id' => $run->id,
            'mobile_money_transaction_id' => $money->id,
            'provider_reference' => $money->provider_reference,
            'internal_reference' => $money->internal_reference,
            'direction' => $money->direction,
            'currency' => $money->currency,
            'system_status' => $money->status,
            'provider_status' => 'successful',
            'system_amount_minor' => $money->amount_minor,
            'provider_amount_minor' => $money->amount_minor,
            'status' => ReconciliationItem::STATUS_EXCEPTION,
            'exception_type' => ReconciliationItem::EXCEPTION_STATUS_MISMATCH,
            'notes' => 'Regression exception.',
        ]);

        Sanctum::actingAs($support);
        $this->patchJson("/api/admin/reconciliation-items/{$item->id}", [
            'status' => ReconciliationItem::STATUS_MATCHED,
            'notes' => 'Do not allow support to force a match.',
        ])->assertStatus(422);
        $this->assertSame(ReconciliationItem::STATUS_EXCEPTION, $item->fresh()->status);

        Sanctum::actingAs($maker);
        $request = $this->postJson("/api/admin/reconciliation-items/{$item->id}/write-off-request", [
            'reason' => 'Provider evidence cannot be recovered after documented investigation.',
            'evidence_hash' => hash('sha256', 'reconciliation-write-off-evidence'),
            'evidence_reference' => 'OPS-REC-001',
        ])->assertCreated();
        $overrideId = (int) $request->json('data.override.id');

        $this->postJson("/api/admin/financial-controls/overrides/{$overrideId}/approve")
            ->assertStatus(409);

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/financial-controls/overrides/{$overrideId}/approve")
            ->assertOk();

        Sanctum::actingAs($maker);
        $this->postJson("/api/admin/reconciliation-items/{$item->id}/write-off", [
            'override_id' => $overrideId,
        ])->assertOk();

        $this->assertSame(ReconciliationItem::STATUS_WRITTEN_OFF, $item->fresh()->status);
        $this->assertSame(MobileMoneyTransaction::STATEMENT_EXCEPTION, $money->fresh()->statement_reconciliation_status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_EXCEPTION, $money->fresh()->reconciliation_status);
        $summary = ReconciliationRun::findOrFail($run->id)->summary;
        $this->assertSame(0, (int) ($summary['exception_count'] ?? -1));
        $this->assertSame(1, (int) ($summary['written_off_count'] ?? -1));
        $this->assertDatabaseHas('financial_control_overrides', ['id' => $overrideId, 'status' => 'applied']);
    }

    public function test_financial_readiness_uses_selected_provider_and_requires_fresh_integrity_and_enabled_disclosure_guard(): void
    {
        config([
            'services.mobile_money.default_provider' => 'ready_test',
            'services.mobile_money.providers.ready_test.adapter' => ReadyFinancialTestAdapter::class,
            'services.mobile_money.providers.ready_test.production_certified' => true,
            'services.sms_gateway' => 'YO',
            'services.yo.base_url' => 'https://sms.example.test',
            'services.yo.account' => 'account',
            'services.yo.password' => 'password',
            'services.identity_verification.url' => 'https://identity.example.test',
            'services.identity_verification.token' => 'identity-token',
            'services.crb.base_url' => 'https://crb.example.test',
            'services.crb.account' => 'client',
            'services.crb.password' => 'secret',
            'services.credit_reference_reporting.url' => 'https://reporting.example.test',
            'services.credit_reference_reporting.token' => 'reporting-token',
            'services.cito.financial_data_certified' => false,
            'opfin.credit.require_funding_pool_assignment' => true,
            'opfin.regulatory.require_credit_disclosure' => true,
            'opfin.regulatory.complaints_email' => 'complaints@example.test',
            'opfin.regulatory.complaints_phone' => '+256700000000',
            'opfin.accounting.efris_required' => false,
            'opfin.accounting.efris_determination_reference' => 'TAX-DETERMINATION-TEST',
            'opfin.accounting.integrity_readiness_max_age_minutes' => 15,
        ]);

        [$lender] = $this->creditFixture(false, true);
        $this->assertSame('Active', $lender->status);

        $runId = DB::table('financial_integrity_runs')->insertGetId([
            'status' => 'balanced',
            'scope' => 'platform',
            'evidence_hash' => hash('sha256', 'financial-readiness-test'),
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ready = app(FinancialReadinessService::class)->report();
        $this->assertTrue($ready['financial_operations_ready']);
        $this->assertSame('ready_test', $ready['checks']['money_movement']['provider']);

        DB::table('financial_integrity_runs')->where('id', $runId)->update([
            'completed_at' => now()->subMinutes(30),
        ]);
        $stale = app(FinancialReadinessService::class)->report();
        $this->assertFalse($stale['financial_operations_ready']);
        $this->assertFalse($stale['checks']['financial_integrity']['fresh']);

        DB::table('financial_integrity_runs')->where('id', $runId)->update([
            'completed_at' => now(),
        ]);
        config(['opfin.regulatory.require_credit_disclosure' => false]);
        $unguarded = app(FinancialReadinessService::class)->report();
        $this->assertFalse($unguarded['financial_operations_ready']);
        $this->assertSame('blocked', $unguarded['checks']['regulated_credit_disclosures']['status']);
    }

    public function test_public_financial_readiness_does_not_expose_findings_or_evidence_hashes(): void
    {
        DB::table('financial_integrity_runs')->insert([
            'status' => 'critical',
            'scope' => 'platform',
            'findings' => json_encode([['secret_internal_evidence' => 'do-not-publish']], JSON_THROW_ON_ERROR),
            'evidence_hash' => hash('sha256', 'sensitive-evidence'),
            'started_at' => now()->subSecond(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/health/financial-ready')->assertStatus(503);
        $body = $response->getContent();

        $this->assertStringNotContainsString('secret_internal_evidence', $body);
        $this->assertStringNotContainsString('evidence_hash', $body);
        $this->assertStringNotContainsString('configured_fields', $body);
    }

    public function test_legacy_unknown_provider_submission_is_a_financial_integrity_exception(): void
    {
        MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 12000,
            'currency' => 'UGX',
            'phone' => '256700006666',
            'idempotency_key' => 'legacy-unknown-001',
            'internal_reference' => 'OPF-LEGACY-UNKNOWN-001',
            'status' => MobileMoneyTransaction::STATUS_PENDING,
            'provider_submission_state' => 'legacy_unknown',
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
            'metadata' => [],
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);

        $run = app(FinancialIntegrityService::class)->run('platform');

        $this->assertSame('exceptions', $run->status);
        $this->assertStringContainsString('legacy_unknown_provider_submission', (string) $run->findings);
        $this->assertDatabaseHas('financial_integrity_alerts', [
            'type' => 'legacy_unknown_provider_submission',
            'status' => 'open',
            'severity' => 'high',
        ]);
    }

    private function createOffer(LoanApplication $application, User $operations)
    {
        return app(ProductionCreditOfferService::class)->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
            'funding_pool_id' => $this->fundingPoolId($operations),
        ]);
    }

    /**
     * @return array{Institution,User,User,LoanProduct,LoanProductTerm,LoanApplication}
     */
    private function creditFixture(bool $withWallet, bool $fullyRegulated = false): array
    {
        $institution = Institution::create([
            'name' => 'Financial Completion Test Lender',
            'address' => 'Kampala',
            'phone' => '256700000711',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'Active',
            'lender_relationship' => 'independent',
            'country' => 'UG',
            'regulator_code' => 'TEST',
            'licence_class' => $fullyRegulated ? 'test_credit_provider' : 'test_credit_provider',
            'authority_basis' => 'licensed',
            'authority_reference' => 'TEST-AUTHORITY-NOT-LIVE',
            'authority_valid_until' => now()->addYear()->toDateString(),
            'rate_change_approval_required' => false,
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => '256700000712',
        ]);
        if ($withWallet) {
            CustomerWallet::create([
                'user_id' => $customer->id,
                'provider' => 'mock',
                'msisdn' => $customer->phone,
                'status' => 'active',
                'verified_at' => now(),
                'is_default_disbursement' => true,
                'is_default_repayment' => true,
            ]);
        }
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        CreditScoreComponent::create([
            'user_id' => $customer->id,
            'source' => 'crb',
            'status' => CreditScoreComponent::STATUS_READY,
            'score' => 800,
            'weight_percent' => 40,
            'source_reference' => 'financial-completion-affordability-'.$customer->id,
            'reason_codes' => ['VERIFIED_INCOME'],
            'raw_payload' => [
                'verified_monthly_income_minor' => 600000,
                'verified_external_obligation_excluding_opfin_minor' => 0,
            ],
            'received_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        $product = LoanProduct::create([
            'name' => 'Completion Controlled Credit',
            'type' => 'Cash',
            'status' => 'Active',
            'institution_id' => $institution->id,
            'country' => 'UG',
            'currency' => 'UGX',
            'product_category' => 'personal_loan',
            'min_amount_minor' => 10000,
            'max_amount_minor' => 1000000,
        ]);
        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 90,
            'status' => 'Active',
        ]);
        $application = LoanApplication::create([
            'user_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 150000,
            'status' => 'Approved',
            'reason' => 'Working capital',
            'distribution_channel' => 'web',
            'approved_at' => now(),
        ]);
        CreditDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $customer->id,
            'decided_by' => $operations->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'requested_amount_minor' => 150000,
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 600000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'ug-credit-policy-completion-test',
            'reason_codes' => ['AFFORDABILITY_REVIEWED', 'CRB_CLEAR'],
            'decision_summary' => 'Approved for financial completion regression.',
            'decided_at' => now(),
        ]);

        return [$institution, $customer, $operations, $product, $term, $application];
    }

    private function fundingPoolId(User $owner): int
    {
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'TEST-LENDER-'.Str::upper(Str::random(8)),
            'name' => 'Completion Test Lender '.Str::random(6),
            'partner_type' => 'financial_institution',
            'institution_id' => $owner->institution_id,
            'country' => 'UG',
            'status' => 'active',
            'regulatory_evidence' => json_encode([
                'licence_number' => 'TEST-LIC-'.Str::upper(Str::random(8)),
                'licence_authority' => 'Test Authority',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('capital_mandates')->insertGetId([
            'reference' => (string) Str::uuid(),
            'owner_user_id' => $owner->id,
            'partner_id' => $partnerId,
            'mandate_type' => 'institutional_credit',
            'name' => 'Completion Test Funding',
            'committed_capital_minor' => 2000000,
            'deployed_capital_minor' => 0,
            'reserved_capital_minor' => 0,
            'status' => 'active',
            'investment_policy' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'approved_by' => $owner->id,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function installPricingPolicy(): void
    {
        DB::table('financial_policies')->insert([
            'code' => 'financial-completion-pricing',
            'policy_type' => 'regulatory_pricing',
            'jurisdiction_country' => 'UG',
            'licence_class' => null,
            'product_scope' => null,
            'version' => 1,
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'rules' => json_encode([
                'interest_basis' => 'original_principal',
                'cycle_days' => ['daily' => 1, 'weekly' => 7, 'monthly' => 30],
                'repayment_frequency_days' => ['daily' => 1, 'weekly' => 7, 'fortnightly' => 14, 'monthly' => 30],
            ], JSON_THROW_ON_ERROR),
            'source_reference' => 'financial-completion-regression',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

final class CreditOfferStatus
{
    public const DISBURSEMENT_PENDING = 'disbursement_pending';
}
