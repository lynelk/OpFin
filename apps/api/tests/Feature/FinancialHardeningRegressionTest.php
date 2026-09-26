<?php

namespace Tests\Feature;

use App\Models\CreditDecision;
use App\Models\CreditScoreComponent;
use App\Models\CustomerWallet;
use App\Models\Institution;
use App\Models\LedgerAccount;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\ProductionCreditOfferService;
use App\Services\ProductionLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialHardeningRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPricingPolicy();
    }

    public function test_financed_fees_create_a_receivable_and_reconcile_after_disbursement_posting(): void
    {
        [$customer, $operations, $application] = $this->approvedApplication();
        $service = app(ProductionCreditOfferService::class);
        $offer = $service->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
            'funding_pool_id' => $this->fundingPoolId($operations),
        ]);

        $accepted = $service->acceptOffer($offer, $customer, ['channel' => 'regression']);
        $payment = $accepted['mobile_money'];
        $payment->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'cpay-financed-fee-regression',
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        $loan = $service->syncDisbursementState($payment->fresh());
        $this->assertNotNull($loan);
        $payment = $payment->fresh();
        $this->assertSame(MobileMoneyTransaction::ACCOUNTING_POSTED, $payment->accounting_status);
        $this->assertNotNull($payment->accounting_posted_at);
        $this->assertSame(MobileMoneyTransaction::STATEMENT_UNRECONCILED, $payment->statement_reconciliation_status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_PENDING, $payment->reconciliation_status);

        $ledger = $this->app['db']->table('ledger_transactions')
            ->where('reference', 'loan.disbursement:credit-offer:'.$offer->offer_reference)
            ->first();
        $this->assertNotNull($ledger);

        $entries = $this->app['db']->table('ledger_entries as e')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.ledger_account_id')
            ->where('e.ledger_transaction_id', $ledger->id)
            ->get(['a.code', 'e.direction', 'e.amount_minor']);

        $this->assertSame(155000, (int) $entries->where('direction', 'debit')->sum('amount_minor'));
        $this->assertSame(155000, (int) $entries->where('direction', 'credit')->sum('amount_minor'));
        $this->assertTrue($entries->contains(fn ($entry) => $entry->code === 'asset.loan_receivable.product_'.$loan->loan_product_id && $entry->direction === 'debit' && (int) $entry->amount_minor === 150000));
        $this->assertTrue($entries->contains(fn ($entry) => $entry->code === 'asset.credit_fee_receivable.product_'.$loan->loan_product_id && $entry->direction === 'debit' && (int) $entry->amount_minor === 5000));
        $this->assertTrue($entries->contains(fn ($entry) => $entry->code === 'cash.mock.disbursement' && $entry->direction === 'credit' && (int) $entry->amount_minor === 150000));
        $this->assertTrue($entries->contains(fn ($entry) => $entry->code === 'liability.credit_fee_clearing.product_'.$loan->loan_product_id && $entry->direction === 'credit' && (int) $entry->amount_minor === 5000));
        $this->assertSame(170000, (int) $this->app['db']->table('credit_repayment_schedule_items')->where('loan_id', $loan->id)->sum('total_due_minor'));
    }

    public function test_affordability_uses_server_projected_debt_service_even_when_declared_obligation_is_zero(): void
    {
        [$customer, $operations, , $decision] = $this->referredApplication();
        $this->installAffordabilityEvidence($customer, 400000);
        Sanctum::actingAs($operations);

        $response = $this->postJson("/api/admin/credit-decisions/{$decision->id}/approve", [
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 400000,
            'estimated_obligation_minor' => 0,
            'policy_version' => 'ug-credit-policy-2026-09',
            'reason_codes' => ['AFFORDABILITY_REVIEWED'],
            'decision_summary' => 'Regression check for server-authoritative debt-service floor.',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.estimated_obligation_minor.0', 'AFFORDABILITY_DSR_EXCEEDED');
        $this->assertSame(CreditDecision::STATUS_REFERRED, $decision->fresh()->status);
    }

    public function test_ledger_rejects_fractional_minor_units_and_inactive_accounts(): void
    {
        $source = User::factory()->create();
        $active = LedgerAccount::create([
            'code' => 'test.asset.active',
            'name' => 'Active test asset',
            'type' => 'asset',
            'currency' => 'UGX',
            'is_active' => true,
        ]);
        $inactive = LedgerAccount::create([
            'code' => 'test.liability.inactive',
            'name' => 'Inactive test liability',
            'type' => 'liability',
            'currency' => 'UGX',
            'is_active' => false,
        ]);

        try {
            app(ProductionLedgerService::class)->post('test:fractional', 'test', $source, [
                ['account_id' => $active->id, 'direction' => 'debit', 'amount_minor' => 100.5],
                ['account_id' => $inactive->id, 'direction' => 'credit', 'amount_minor' => 100.5],
            ]);
            $this->fail('Fractional minor units must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('positive integer minor units', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inactive');
        app(ProductionLedgerService::class)->post('test:inactive', 'test', $source, [
            ['account_id' => $active->id, 'direction' => 'debit', 'amount_minor' => 100],
            ['account_id' => $inactive->id, 'direction' => 'credit', 'amount_minor' => 100],
        ]);
    }

    private function fundingPoolId(User $owner): int
    {
        $partnerId = DB::table('partners')->insertGetId([
            'code' => 'TEST-LENDER-'.Str::upper(Str::random(8)),
            'name' => 'Test Licensed Lender '.Str::random(6),
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
            'name' => 'Test Third-party Funding',
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

    private function approvedApplication(): array
    {
        [$customer, $operations, $application, $decision] = $this->referredApplication();
        $this->installAffordabilityEvidence($customer, 600000);
        $decision->update([
            'status' => CreditDecision::STATUS_APPROVED,
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 600000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'ug-credit-policy-2026-09',
            'reason_codes' => ['AFFORDABILITY_REVIEWED', 'CRB_CLEAR'],
            'decision_summary' => 'Approved after documented review.',
            'decided_by' => $operations->id,
            'decided_at' => now(),
        ]);
        $application->update(['status' => 'Approved', 'approved_at' => now()]);

        return [$customer, $operations, $application, $decision];
    }

    private function installAffordabilityEvidence(User $user, int $incomeMinor): void
    {
        CreditScoreComponent::create([
            'user_id' => $user->id,
            'source' => 'crb',
            'status' => CreditScoreComponent::STATUS_READY,
            'score' => 800,
            'weight_percent' => 40,
            'source_reference' => 'financial-hardening-affordability-'.$user->id,
            'reason_codes' => ['VERIFIED_INCOME'],
            'raw_payload' => [
                'verified_monthly_income_minor' => $incomeMinor,
                'verified_external_obligation_excluding_opfin_minor' => 0,
            ],
            'received_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    private function installPricingPolicy(): void
    {
        DB::table('financial_policies')->insert([
            'code' => 'test-regulatory-pricing',
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
            'source_reference' => 'financial-signoff-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function referredApplication(): array
    {
        $institution = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-AUTHORITY-NOT-LIVE', 'regulator_code' => 'TEST',
            'name' => 'Financial Hardening Institution',
            'address' => 'Kampala',
            'phone' => '256700000711',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'institution_id' => $institution->id]);
        CustomerWallet::create([
            'user_id' => $customer->id,
            'provider' => 'mock',
            'msisdn' => $customer->phone ?: '256700000712',
            'status' => 'active',
            'verified_at' => now(),
            'is_default_disbursement' => true,
            'is_default_repayment' => true,
        ]);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'institution_id' => $institution->id]);
        $product = LoanProduct::create(['name' => 'Hardening Credit', 'type' => 'Cash', 'institution_id' => $institution->id]);
        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 30,
            'status' => 'Active',
        ]);
        $application = LoanApplication::create([
            'user_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 150000,
            'status' => 'Referred',
            'reason' => 'Working capital',
        ]);
        $decision = CreditDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $customer->id,
            'status' => CreditDecision::STATUS_REFERRED,
            'requested_amount_minor' => 150000,
            'approved_amount_minor' => 150000,
            'reason_codes' => ['KYC_VERIFIED', 'CONSENT_GRANTED', 'CRB_CLEAR'],
            'decision_summary' => 'Prerequisite gates passed and operations review is required.',
            'decided_at' => now(),
        ]);

        return [$customer, $operations, $application, $decision];
    }
}
