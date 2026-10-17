<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionalEclService;
use App\Services\InstitutionalExposureAccountingService;
use App\Services\InstitutionalFinanceAssuranceService;
use App\Services\ProductionLedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class InstitutionalFinanceAssuranceTest extends TestCase
{
    use RefreshDatabase;

    private function evidence(): array
    {
        $institution = Institution::create([
            'name' => 'Assurance QA Institution', 'address' => 'Kampala',
            'phone' => '256700008865', 'email' => 'assurance-test@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'SYNTHETIC-QA-ONLY',
            'regulator_code' => 'TEST',
        ]);
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $date = now()->toDateString();
        $ecl = app(InstitutionalEclService::class);
        $policy = $ecl->stagePolicy($institution->id, [
            'version' => 'independent-validation-required', 'effective_from' => $date,
            'currency' => 'UGX', 'stage2_dpd' => 30, 'stage3_dpd' => 90,
            'discount_bps' => 0, 'methodology_reference' => 'SYNTHETIC-NO-IFRS-CERTIFICATION',
            'scenarios' => [
                ['weight_bps' => 7000, 'pd_12m_bps' => 250, 'pd_lifetime_bps' => 1000, 'pd_impaired_bps' => 9000, 'lgd_bps' => 5000],
                ['weight_bps' => 3000, 'pd_12m_bps' => 500, 'pd_lifetime_bps' => 1800, 'pd_impaired_bps' => 9500, 'lgd_bps' => 6000],
            ],
        ], $maker);
        $ecl->approvePolicy($policy, $checker);
        $source = $ecl->stageExternal($institution->id, [
            'external_loan_reference' => 'LENDER-ASSET-200000', 'as_of_date' => $date,
            'currency' => 'UGX', 'principal_outstanding_minor' => 200000,
            'days_past_due' => 0, 'sicr' => false, 'credit_impaired' => false,
            'contractual_maturity_date' => now()->addYear()->toDateString(),
            'source_evidence' => 'INDEPENDENT-LEDGER-SOURCE-TEST',
        ], $maker);
        $ecl->approveExternal($source, $checker);
        $run = $ecl->build($institution->id, $date, 1, 200000, 'TEST-FULL-SOURCE-BOOK', $maker);
        $ecl->approveRun($run, $checker);
        $ecl->postRun($run, $checker);
        $custody = app(InstitutionalExposureAccountingService::class);
        $cash = $custody->partnerProviderCashAccount($institution->id, 'providerqa', 'UGX');
        $held = $custody->partnerFundsAccount($institution->id, 'UGX');
        app(ProductionLedgerService::class)->post(
            'qa:original-institution-cash-funding', 'partner.funds.received', $maker,
            [
                ['account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 150000],
                ['account_id' => $held->id, 'direction' => 'credit', 'amount_minor' => 150000],
            ], $maker, 'UGX'
        );

        return [$institution, $maker, $checker, $run, $date];
    }

    private function input(int $institutionId, int $run, string $date, int $bank = 150000, int $principal = 200000): array
    {
        return [
            'institution_id' => $institutionId, 'ecl_run_id' => $run,
            'as_of_date' => $date, 'currency' => 'UGX',
            'provider_statements' => [[
                'provider' => 'providerqa', 'balance_minor' => $bank,
                'statement_sha256' => str_repeat('a', 64),
                'statement_reference' => 'BANK-STATEMENT-EXTERNAL-TEST',
            ]],
            'external_payable_minor' => $bank,
            'external_principal_minor' => $principal,
            'external_loan_count' => 1,
            'lender_ledger_reference' => 'INDEPENDENT-LENDER-LEDGER-TEST',
            'lender_ledger_sha256' => str_repeat('b', 64),
        ];
    }

    public function test_exact_three_way_reconciliation_requires_independent_checker_and_five_distinct_reviewers(): void
    {
        [$institution, $maker, $checker, $run, $date] = $this->evidence();
        $service = app(InstitutionalFinanceAssuranceService::class);
        $id = $service->stage($this->input($institution->id, $run, $date), $maker);
        $row = DB::table('institution_finance_reconciliations')->find($id);
        $this->assertTrue(json_decode($row->control_results, true)['balanced']);
        $this->assertSame('draft', $row->status);
        $this->assertSame(0, DB::table('institution_finance_reviews')->count());
        try {
            $service->approve($id, $maker);
            $this->fail('Source maker may not approve.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('independent', $e->getMessage());
        }
        $service->approve($id, $checker);
        $this->assertSame('reconciled_pending_signoff',
            DB::table('institution_finance_reconciliations')->find($id)->status);
        foreach (InstitutionalFinanceAssuranceService::CAPACITIES as $capacity) {
            $signer = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
            $service->sign($id, $capacity, 'TEST-REVIEWER-REPORT-'.$capacity, str_repeat('c', 64),
                'I have reviewed the synthetic source records only; no actual audit was performed.', $signer);
        }
        $this->assertSame(5, DB::table('institution_finance_reviews')->where('reconciliation_id', $id)->count());
        $this->assertSame('reconciled_pending_signoff',
            DB::table('institution_finance_reconciliations')->find($id)->status);
        $this->expectException(QueryException::class);
        DB::table('institution_finance_reconciliations')->where('id', $id)
            ->update(['external_payable_minor' => 123]);
    }

    public function test_material_bank_and_lender_variances_are_retained_but_never_approved(): void
    {
        [$institution, $maker, $checker, $run, $date] = $this->evidence();
        $service = app(InstitutionalFinanceAssuranceService::class);
        $input = $this->input($institution->id, $run, $date, 100000, 150000);
        $id = $service->stage($input, $maker);
        $results = json_decode(DB::table('institution_finance_reconciliations')->find($id)->control_results, true);
        $this->assertFalse($results['balanced']);
        $this->assertSame(-50000, $results['provider_control']['providerqa']['variance_minor']);
        $this->assertSame(-50000, $results['lender_vs_mis_variance_minor']);
        $this->expectException(InvalidArgumentException::class);
        $service->approve($id, $checker);
    }

    public function test_independent_review_cannot_be_duplicated_by_one_person(): void
    {
        [$institution, $maker, $checker, $run, $date] = $this->evidence();
        $service = app(InstitutionalFinanceAssuranceService::class);
        $id = $service->stage($this->input($institution->id, $run, $date), $maker);
        $service->approve($id, $checker);
        $reviewer = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $statement = 'I independently reviewed this synthetic finance record solely for repeatability testing.';
        $service->sign($id, 'finance', 'QA-REVIEW-FINANCE-REFERENCE', str_repeat('c', 64), $statement, $reviewer);
        $this->expectException(InvalidArgumentException::class);
        $service->sign($id, 'risk', 'QA-REVIEW-RISK-REFERENCE', str_repeat('d', 64), $statement, $reviewer);
    }

    public function test_approval_rechecks_ledgers_after_new_cash_posting(): void
    {
        [$institution, $maker, $checker, $run, $date] = $this->evidence();
        $service = app(InstitutionalFinanceAssuranceService::class);
        $id = $service->stage($this->input($institution->id, $run, $date), $maker);
        $custody = app(InstitutionalExposureAccountingService::class);
        $cash = $custody->partnerProviderCashAccount($institution->id, 'providerqa', 'UGX');
        $held = $custody->partnerFundsAccount($institution->id, 'UGX');
        app(ProductionLedgerService::class)->post('qa:cash-posting-after-source', 'partner.funds.received', $maker, [
            ['account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 100],
            ['account_id' => $held->id, 'direction' => 'credit', 'amount_minor' => 100],
        ], $maker, 'UGX');
        $this->expectException(InvalidArgumentException::class);
        $service->approve($id, $checker);
    }

    public function test_two_institution_provider_prefixes_never_cross_contaminate(): void
    {
        [$institution, $maker, $checker, $run, $date] = $this->evidence();
        $other = Institution::create([
            'name' => 'Different Institution', 'address' => 'Jinja',
            'phone' => '256700008866', 'email' => 'other-assurance@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'SYNTHETIC',
            'regulator_code' => 'TEST',
        ]);
        $otherCash = app(InstitutionalExposureAccountingService::class)
            ->partnerProviderCashAccount($other->id, 'providerqa', 'UGX');
        $otherHeld = app(InstitutionalExposureAccountingService::class)
            ->partnerFundsAccount($other->id, 'UGX');
        app(ProductionLedgerService::class)->post('qa:separate-custody', 'partner.funds.received', $maker, [
            ['account_id' => $otherCash->id, 'direction' => 'debit', 'amount_minor' => 99999],
            ['account_id' => $otherHeld->id, 'direction' => 'credit', 'amount_minor' => 99999],
        ], $maker, 'UGX');
        $id = app(InstitutionalFinanceAssuranceService::class)->stage($this->input($institution->id, $run, $date), $maker);
        $results = json_decode(DB::table('institution_finance_reconciliations')->find($id)->control_results, true);
        $this->assertTrue($results['balanced']);
        $this->assertSame(150000, $results['provider_control']['providerqa']['ledger_minor']);
        $this->assertArrayNotHasKey('providerqa_other', $results['provider_control']);
    }
}
