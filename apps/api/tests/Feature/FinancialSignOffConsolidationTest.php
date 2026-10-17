<?php

namespace Tests\Feature;

use App\Models\CreditDecision;
use App\Models\CreditScoreComponent;
use App\Models\Institution;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\CreditEconomicsService;
use App\Services\CreditFeeRecognitionService;
use App\Services\CreditWriteOffService;
use App\Services\DefaultInterestService;
use App\Services\EarlySettlementService;
use App\Services\FinancialAccountingPeriodService;
use App\Services\LoanImpairmentService;
use App\Services\ProductionCreditOfferService;
use App\Services\ProductionLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class FinancialSignOffConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Finance fixtures must never submit real payment-provider instructions.
        config()->set('services.mobile_money.default_provider', 'mock');
        $this->installPricingPolicy();
    }

    public function test_canonical_credit_economics_uses_dated_apr_and_avoids_one_day_monthly_stub(): void
    {
        $term = new LoanProductTerm([
            'interest_rate' => 2.8,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 61,
            'status' => 'Active',
        ]);

        $quote = app(CreditEconomicsService::class)->quote($term, 50000, [
            'access_fee_minor' => 1500,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
        ]);

        $this->assertSame([30, 61], array_column($quote['schedule'], 'due_offset_days'));
        $this->assertSame(50000, array_sum(array_column($quote['schedule'], 'principal_minor')));
        $this->assertSame($quote['total_repayment_minor'], array_sum(array_column($quote['schedule'], 'total_due_minor')));
        $this->assertSame('dated-cash-flow-bisection-v1', $quote['apr_algorithm_version']);
        $this->assertSame('frequency-stub-v2', $quote['schedule_algorithm_version']);
        $this->assertGreaterThan($quote['simple_annualised_cost_percent'], $quote['equivalent_apr_percent']);
    }

    public function test_ledger_account_identity_is_currency_safe_and_postings_are_immutable(): void
    {
        $ugx = LedgerAccount::create([
            'code' => 'test.cash.bank',
            'name' => 'Test bank cash UGX',
            'type' => 'asset',
            'currency' => 'UGX',
            'is_active' => true,
        ]);
        LedgerAccount::create([
            'code' => 'test.cash.bank',
            'name' => 'Test bank cash USD',
            'type' => 'asset',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $offset = LedgerAccount::create([
            'code' => 'test.equity.offset',
            'name' => 'Test offset',
            'type' => 'equity',
            'currency' => 'UGX',
            'is_active' => true,
        ]);
        $source = User::factory()->create();

        $this->assertSame(
            2,
            LedgerAccount::query()->where('code', 'test.cash.bank')->count(),
        );

        $posted = app(ProductionLedgerService::class)->post(
            'test:immutable-ledger',
            'test.posting',
            $source,
            [
                ['account_id' => $ugx->id, 'direction' => 'debit', 'amount_minor' => 1000],
                ['account_id' => $offset->id, 'direction' => 'credit', 'amount_minor' => 1000],
            ],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('immutable');
        $posted->update(['event_type' => 'test.changed']);
    }

    public function test_stage_three_impairment_write_off_consumes_allowance_and_stops_future_accruals(): void
    {
        [$customer, $operations, $loan] = $this->productionLoan();

        $assessment = app(LoanImpairmentService::class)->assess(
            $loan,
            now(),
            150000,
            'stage_3',
            'ecl-policy-test-v1',
            ['method' => 'finance-approved-test', 'reviewed' => true],
            $operations,
        );
        $this->assertSame(150000, $assessment->expected_credit_loss_minor);

        $writeOff = app(CreditWriteOffService::class)->writeOff(
            $loan,
            'write-off-policy-test-v1',
            ['approval_reference' => 'TEST-WO-001', 'recovery_continues' => true],
            $operations,
        );

        $this->assertTrue($writeOff->legal_obligation_preserved);
        $this->assertSame('Written Off', $loan->fresh()->status);
        $this->assertSame(0, $this->accountBalance('asset.loan_receivable.product_'.$loan->loan_product_id, 'UGX', 'debit'));
        $this->assertSame(0, $this->accountBalance('contra_asset.credit_loss_allowance.product_'.$loan->loan_product_id, 'UGX', 'credit'));
        $this->assertSame(0, $this->accountBalance('asset.credit_fee_receivable.product_'.$loan->loan_product_id, 'UGX', 'debit'));
        $this->assertSame(0, $this->accountBalance('liability.credit_fee_clearing.product_'.$loan->loan_product_id, 'UGX', 'credit'));

        $this->assertSame(0, app(CreditFeeRecognitionService::class)->recognise($loan->fresh()));
        $this->assertSame(
            0,
            app(DefaultInterestService::class)->outstandingMinor(
                app(DefaultInterestService::class)->accrue($loan->fresh()),
            ),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('recovery path');
        app(EarlySettlementService::class)->quote($loan->fresh(), $customer);
    }

    public function test_partner_owned_exposure_posts_only_custodial_cash_and_partner_liability(): void
    {
        [, $operations, $loan] = $this->productionLoan();
        DB::table('loan_ecl_ownerships')->insert([
            'loan_id' => $loan->id, 'institution_id' => $loan->institution_id,
            'owner_type' => 'partner', 'opfin_share_bps' => 0,
            'agreement_reference' => 'TEST-AGENCY-AGREEMENT',
            'status' => 'approved', 'created_by' => $operations->id,
            'approved_by' => $operations->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cash = app(\App\Services\InstitutionalExposureAccountingService::class)
            ->partnerProviderCashAccount((int) $loan->institution_id, 'test', 'UGX');
        $held = LedgerAccount::create([
            'code' => 'liability.partner_funds_held.institution_'.$loan->institution_id,
            'name' => 'Lender funds held', 'type' => 'liability', 'currency' => 'UGX',
            'is_active' => true,
        ]);
        $journal = app(ProductionLedgerService::class)->post(
            'test:partner-only-custody', 'loan.repayment', $loan,
            [
                ['account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 1500],
                ['account_id' => $held->id, 'direction' => 'credit', 'amount_minor' => 1500],
            ],
            $operations, 'UGX', ['loan_id' => $loan->id, 'ownership_type' => 'partner'],
        );
        $this->assertCount(2, $journal->entries);
        $this->assertEqualsCanonicalizing([$cash->id, $held->id],
            $journal->entries->pluck('ledger_account_id')->all());

        app(ProductionLedgerService::class)->post(
            'test:partner-custody-reversal', 'loan.repayment.reversal', $loan,
            [
                ['account_id' => $cash->id, 'direction' => 'credit', 'amount_minor' => 1500],
                ['account_id' => $held->id, 'direction' => 'debit', 'amount_minor' => 1500],
            ],
            $operations, 'UGX', ['loan_id' => $loan->id, 'ownership_type' => 'partner'],
        );
        $this->assertSame(0, (int) DB::table('ledger_entries')->where('ledger_account_id', $held->id)
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) AS balance")
            ->value('balance'));
    }

    public function test_partner_cannot_spend_custodial_cash_from_another_provider(): void
    {
        [, $operations, $loan] = $this->productionLoan();
        $accounts = app(\App\Services\InstitutionalExposureAccountingService::class);
        $otherProvider = $accounts->partnerProviderCashAccount((int) $loan->institution_id, 'provider_a', 'UGX');
        $payoutProvider = $accounts->partnerProviderCashAccount((int) $loan->institution_id, 'provider_b', 'UGX');
        $held = $accounts->partnerFundsAccount((int) $loan->institution_id, 'UGX');
        app(ProductionLedgerService::class)->post(
            'test:partner-prefunded-in-provider-a', 'partner.funds.received', $operations,
            [
                ['account_id' => $otherProvider->id, 'direction' => 'debit', 'amount_minor' => 5000],
                ['account_id' => $held->id, 'direction' => 'credit', 'amount_minor' => 5000],
            ], $operations, 'UGX'
        );
        $before = DB::table('ledger_transactions')->count();
        try {
            app(ProductionLedgerService::class)->post(
                'test:wrong-provider-payout', 'partner.funds.disbursement', $operations,
                [
                    ['account_id' => $held->id, 'direction' => 'debit', 'amount_minor' => 2500],
                    ['account_id' => $payoutProvider->id, 'direction' => 'credit', 'amount_minor' => 2500],
                ], $operations, 'UGX'
            );
            $this->fail('Funds on Provider A must not be spent via Provider B without a reconciled treasury transfer.');
        } catch (InvalidArgumentException $error) {
            $this->assertStringContainsString('custody cash cannot be spent', $error->getMessage());
        }
        $this->assertSame($before, DB::table('ledger_transactions')->count());
    }

    public function test_independently_matched_partner_prefunding_posts_cash_and_liability_exactly_once(): void
    {
        [, $operations, $loan] = $this->productionLoan();
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $money = MobileMoneyTransaction::create([
            'user_id' => $operations->id, 'institution_id' => $loan->institution_id,
            'provider' => 'mock', 'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 90000, 'currency' => 'UGX', 'phone' => '256700000112',
            'idempotency_key' => 'institution-funds-test-'.$loan->id,
            'internal_reference' => 'institution-funds-test-'.$loan->id,
            'provider_reference' => 'BANK-PREFUND-TEST-'.$loan->id,
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
            'statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_MATCHED,
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_UNPOSTED,
            'metadata' => ['purpose' => 'institutional_prefunding'],
        ]);
        $treasury = app(\App\Services\InstitutionalExposureAccountingService::class);
        $draft = $treasury->stageFundingReceipt($money->id, 'RECONCILED-PROVIDER-STATEMENT-TEST', $operations);
        try {
            $treasury->approveAndPostFundingReceipt($draft, $operations);
            $this->fail('Funding maker must not approve their own bank receipt.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('separate treasury checker', $e->getMessage());
        }
        $journalId = $treasury->approveAndPostFundingReceipt($draft, $checker);
        $journal = \App\Models\LedgerTransaction::query()->with('entries')->findOrFail($journalId);
        $this->assertSame('partner.funds.received', $journal->event_type);
        $this->assertCount(2, $journal->entries);
        $this->assertSame('posted', DB::table('institution_ecl_funding_receipts')->find($draft)->status);
        $this->assertSame(MobileMoneyTransaction::ACCOUNTING_POSTED, $money->fresh()->accounting_status);
        $account = $treasury->partnerFundsAccount((int) $loan->institution_id, 'UGX');
        $this->assertSame(90000, (int) DB::table('ledger_entries')->where('ledger_account_id', $account->id)
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) AS balance")
            ->value('balance'));
        $duplicateProviderMovement = MobileMoneyTransaction::create([
            'user_id' => $operations->id, 'institution_id' => $loan->institution_id,
            'provider' => 'mock', 'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 90000, 'currency' => 'UGX', 'phone' => '256700000113',
            'idempotency_key' => 'institution-funds-other-event-'.$loan->id,
            'internal_reference' => 'institution-funds-other-event-'.$loan->id,
            'provider_reference' => 'BANK-PREFUND-TEST-'.$loan->id,
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
            'statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_MATCHED,
            'accounting_status' => MobileMoneyTransaction::ACCOUNTING_UNPOSTED,
            'metadata' => ['purpose' => 'institutional_prefunding'],
        ]);
        try {
            $treasury->stageFundingReceipt($duplicateProviderMovement->id, 'REPLAYED-SOURCE', $operations);
            $this->fail('A second provider message cannot double-credit the same underlying bank transfer.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already been allocated', $e->getMessage());
        }
        $this->assertSame(1, DB::table('institution_ecl_funding_receipts')->count());
        $this->expectException(InvalidArgumentException::class);
        $treasury->approveAndPostFundingReceipt($draft, $checker);
    }

    public function test_partner_fiduciary_liability_cannot_be_spent_without_prior_verified_cash(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $held = LedgerAccount::create([
            'code' => 'liability.partner_funds_held.institution_9998',
            'name' => 'Test lender funds awaiting receipt',
            'type' => 'liability', 'currency' => 'UGX', 'is_active' => true,
        ]);
        $cash = LedgerAccount::create([
            'code' => 'cash.test.prefunding', 'name' => 'Test payment cash',
            'type' => 'asset', 'currency' => 'UGX', 'is_active' => true,
        ]);
        $prior = DB::table('ledger_transactions')->count();
        try {
            app(ProductionLedgerService::class)->post(
                'test:partner-unfunded-payout', 'partner.funds.disbursement', $actor,
                [
                    ['account_id' => $held->id, 'direction' => 'debit', 'amount_minor' => 10000],
                    ['account_id' => $cash->id, 'direction' => 'credit', 'amount_minor' => 10000],
                ], $actor, 'UGX',
            );
            $this->fail('A partner funds payable cannot be spent before cash receipt is evidenced.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be overdrawn', $e->getMessage());
        }
        $this->assertSame($prior, DB::table('ledger_transactions')->count());
    }

    public function test_partner_risk_owner_blocks_platform_impairment_income_and_asset_entries(): void
    {
        [, $operations, $loan] = $this->productionLoan();
        DB::table('loan_ecl_ownerships')->insert([
            'loan_id' => $loan->id, 'institution_id' => $loan->institution_id,
            'owner_type' => 'partner', 'opfin_share_bps' => 0,
            'agreement_reference' => 'TEST-AGENCY-ACCOUNTING',
            'status' => 'approved', 'created_by' => $operations->id,
            'approved_by' => $operations->id, 'approved_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $cash = LedgerAccount::create([
            'code' => 'cash.test.forbidden', 'name' => 'Test cash',
            'type' => 'asset', 'currency' => 'UGX', 'is_active' => true,
        ]);
        $income = LedgerAccount::create([
            'code' => 'income.test.partner', 'name' => 'Forbidden lender income',
            'type' => 'income', 'currency' => 'UGX', 'is_active' => true,
        ]);
        try {
            app(ProductionLedgerService::class)->post(
                'test:partner-income-forbidden', 'loan.repayment', $loan,
                [
                    ['account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 500],
                    ['account_id' => $income->id, 'direction' => 'credit', 'amount_minor' => 500],
                ],
                $operations, 'UGX', ['loan_id' => $loan->id, 'ownership_type' => 'partner'],
            );
            $this->fail('Partner financial income must never be recognised as OpFin revenue.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('platform receivable, income', $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        app(LoanImpairmentService::class)->assess($loan, now(), 500,
            'stage_1', 'test-partner-ineligible', ['risk_owner' => 'partner'], $operations);
    }

    public function test_closed_accounting_period_rejects_a_posting_inside_that_period(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $periodDate = now()->subDay()->startOfDay();

        DB::table('financial_integrity_runs')->insert([
            'status' => 'balanced',
            'scope' => 'platform',
            'evidence_hash' => hash('sha256', 'test-balanced-run'),
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(FinancialAccountingPeriodService::class)->close(
            $periodDate,
            $periodDate,
            $actor,
            'Financial sign-off regression test.',
        );

        $asset = LedgerAccount::create([
            'code' => 'test.closed.asset',
            'name' => 'Closed-period asset',
            'type' => 'asset',
            'currency' => 'UGX',
            'is_active' => true,
        ]);
        $equity = LedgerAccount::create([
            'code' => 'test.closed.equity',
            'name' => 'Closed-period equity',
            'type' => 'equity',
            'currency' => 'UGX',
            'is_active' => true,
        ]);

        Carbon::setTestNow($periodDate->copy()->addHours(12));
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('closed accounting period');
            app(ProductionLedgerService::class)->post(
                'test:closed-period',
                'test.posting',
                $actor,
                [
                    ['account_id' => $asset->id, 'direction' => 'debit', 'amount_minor' => 100],
                    ['account_id' => $equity->id, 'direction' => 'credit', 'amount_minor' => 100],
                ],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    private function productionLoan(): array
    {
        $institution = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-AUTHORITY-NOT-LIVE', 'regulator_code' => 'TEST',
            'name' => 'Financial Sign-Off Institution',
            'address' => 'Kampala',
            'phone' => '256700009001',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => '256700009002',
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        $this->installAffordabilityEvidence($customer, 600000);
        $product = LoanProduct::create([
            'name' => 'Financial Sign-Off Credit',
            'type' => 'Cash',
            'institution_id' => $institution->id,
        ]);
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
            'status' => 'Approved',
            'reason' => 'Financial sign-off test',
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
            'policy_version' => 'financial-signoff-test-v1',
            'reason_codes' => ['AFFORDABILITY_REVIEWED'],
            'decision_summary' => 'Approved for financial sign-off regression.',
            'decided_at' => now(),
        ]);

        $offers = app(ProductionCreditOfferService::class);
        $offer = $offers->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
            'funding_pool_id' => $this->fundingPoolId($operations),
        ]);
        $accepted = $offers->acceptOffer($offer, $customer, ['channel' => 'test']);
        $payout = $accepted['mobile_money'];
        $payout->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-signoff-payout-'.$offer->id,
        ]);
        $loan = $offers->syncDisbursementState($payout->fresh());

        $this->assertInstanceOf(Loan::class, $loan);

        return [$customer, $operations, $loan];
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
            'name' => 'Financial Sign-off Funding',
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

    private function installAffordabilityEvidence(User $user, int $incomeMinor): void
    {
        CreditScoreComponent::create([
            'user_id' => $user->id,
            'source' => 'crb',
            'status' => CreditScoreComponent::STATUS_READY,
            'score' => 800,
            'weight_percent' => 40,
            'source_reference' => 'financial-signoff-affordability-'.$user->id,
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

    private function accountBalance(string $code, string $currency, string $normal): int
    {
        $totals = DB::table('ledger_entries as e')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.ledger_account_id')
            ->where('a.code', $code)
            ->where('a.currency', $currency)
            ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'debit' THEN e.amount_minor ELSE 0 END), 0) AS debits")
            ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'credit' THEN e.amount_minor ELSE 0 END), 0) AS credits")
            ->first();

        $debits = (int) ($totals->debits ?? 0);
        $credits = (int) ($totals->credits ?? 0);

        return $normal === 'debit' ? $debits - $credits : $credits - $debits;
    }
}
