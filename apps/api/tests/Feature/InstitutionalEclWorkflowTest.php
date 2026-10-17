<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Services\InstitutionalEclCalculator;
use App\Services\InstitutionalEclService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class InstitutionalEclWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function scenarios(): array
    {
        return [
            ['weight_bps' => 6000, 'pd_12m_bps' => 200, 'pd_lifetime_bps' => 1000,
                'pd_impaired_bps' => 9000, 'lgd_bps' => 5000],
            ['weight_bps' => 4000, 'pd_12m_bps' => 400, 'pd_lifetime_bps' => 2000,
                'pd_impaired_bps' => 9500, 'lgd_bps' => 6000],
        ];
    }

    public function test_weighted_ecl_uses_stage_and_scenarios_without_hardcoded_loss_rates(): void
    {
        $calculator = app(InstitutionalEclCalculator::class);
        $first = $calculator->calculate(100000, 0, false, false, 24, 30, 90, 0, $this->scenarios());
        $second = $calculator->calculate(100000, 35, false, false, 24, 30, 90, 0, $this->scenarios());
        $third = $calculator->calculate(100000, 0, false, true, 24, 30, 90, 0, $this->scenarios());
        $this->assertSame('stage_1', $first['stage']);
        $this->assertSame(1560, $first['expected_credit_loss_minor']);
        $this->assertSame('stage_2', $second['stage']);
        $this->assertSame(7800, $second['expected_credit_loss_minor']);
        $this->assertSame('stage_3', $third['stage']);
        $this->assertSame(49800, $third['expected_credit_loss_minor']);
        $this->assertSame('12_month_default_events', $first['evidence']['pd_horizon']);
    }

    public function test_invalid_scenario_weights_are_rejected(): void
    {
        $scenarios = $this->scenarios();
        $scenarios[0]['weight_bps'] = 5999;
        $this->expectException(InvalidArgumentException::class);
        app(InstitutionalEclCalculator::class)->validateScenarios($scenarios);
    }

    public function test_external_lender_reporting_requires_independent_approvals_and_never_posts_opfin_ledger(): void
    {
        $institution = Institution::create([
            'name' => 'Test Partner ECL', 'address' => 'Kampala',
            'phone' => '256700009991', 'email' => 'ecl-partner-test@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN, 'institution_id' => $institution->id]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN, 'institution_id' => $institution->id]);
        $engine = app(InstitutionalEclService::class);
        $policy = $engine->stagePolicy($institution->id, [
            'version' => 'test-ecl-v1', 'effective_from' => now()->subMonth()->toDateString(),
            'currency' => 'UGX', 'stage2_dpd' => 30, 'stage3_dpd' => 90,
            'discount_bps' => 0, 'methodology_reference' => 'TEST-DOCUMENT-NOT-ACTUAL-APPROVAL',
            'scenarios' => $this->scenarios(),
        ], $maker);

        try {
            $engine->approvePolicy($policy, $maker);
            $this->fail('Maker must not approve their own policy.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('maker', strtolower($e->getMessage()));
        }
        $engine->approvePolicy($policy, $checker);

        $asOf = now()->toDateString();
        $snapshot = $engine->stageExternal($institution->id, [
            'external_loan_reference' => 'PARTNER-CASE-001',
            'as_of_date' => $asOf, 'currency' => 'UGX',
            'principal_outstanding_minor' => 100000, 'days_past_due' => 35,
            'sicr' => true, 'credit_impaired' => false,
            'contractual_maturity_date' => now()->addMonths(24)->toDateString(),
            'source_evidence' => 'TEST-APPROVED-SOURCE-001',
        ], $maker);
        try {
            $engine->approveExternal($snapshot, $maker);
            $this->fail('Source maker must not approve own import.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('independent', $e->getMessage());
        }
        $engine->approveExternal($snapshot, $checker);
        $run = $engine->build($institution->id, $asOf, 1, 100000, 'TEST-SOURCE-CONTROLS-001', $maker);
        $preview = $engine->details($run);
        $this->assertSame(7800, (int) $preview['run']->total_ecl_minor);
        $this->assertSame(0, (int) $preview['run']->platform_ecl_minor);
        $this->assertSame('partner', $preview['items'][0]->owner_type);
        $this->assertSame('stage_2', $preview['items'][0]->stage);

        try {
            $engine->approveRun($run, $maker);
            $this->fail('Maker must not approve their own provision report.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Independent', $e->getMessage());
        }
        $engine->approveRun($run, $checker);
        $before = DB::table('ledger_transactions')->count();
        $engine->postRun($run, $checker);
        $this->assertSame($before, DB::table('ledger_transactions')->count());
        $this->assertSame('finalised', $engine->details($run)['run']->status);

        $this->actingAs($maker)->get('/api/admin/institutional-ecl/runs/'.$run.'/export/loans')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($maker)->getJson('/api/admin/institutional-ecl/runs/'.$run)->assertOk();
    }


    public function test_csv_batch_is_atomic_requires_source_totals_and_has_immutable_evidence(): void
    {
        $institution = Institution::create([
            'name' => 'CSV Credit Partner', 'address' => 'Kampala',
            'phone' => '256700009993', 'email' => 'csv-ecl-test@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $service = app(InstitutionalEclService::class);
        $asOf = now()->toDateString();
        $csv = 'external_loan_reference,principal_outstanding_minor,days_past_due,sicr,credit_impaired,contractual_maturity_date'."\n"
            .'CSV-1,100000,35,1,0,2027-12-31'."\n"
            .'CSV-2,50000,0,0,0,2027-12-31'."\n";
        try {
            $service->stageExternalCsv($institution->id, $asOf, 'UGX', 'TEST-MIS-CONTROLS', $csv, 2, 149999, $maker);
            $this->fail('An unmatched source control total must fail closed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reconcile', $e->getMessage());
        }
        $this->assertSame(0, DB::table('institution_ecl_import_batches')->count());
        $batch = $service->stageExternalCsv($institution->id, $asOf, 'UGX', 'TEST-MIS-CONTROLS', $csv, 2, 150000, $maker);
        $this->assertSame(2, DB::table('institution_ecl_external_snapshots')->where('import_batch_id', $batch)->count());
        try {
            $service->approveExternalBatch($batch, $maker);
            $this->fail('Batch source creator must not be their own checker.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('independent', $e->getMessage());
        }
        $this->assertSame(2, $service->approveExternalBatch($batch, $checker));
        $this->assertSame('approved', DB::table('institution_ecl_import_batches')->find($batch)->status);

        $this->expectException(\\Illuminate\\Database\\QueryException::class);
        $snapshotId = DB::table('institution_ecl_external_snapshots')
            ->where('import_batch_id', $batch)->value('id');
        DB::table('institution_ecl_external_snapshots')
            ->where('id', $snapshotId)->update(['principal_outstanding_minor' => 9999]);
    }

    public function test_correction_and_restatement_preserve_first_signed_partner_portfolio_and_post_no_platform_journals(): void
    {
        $institution = Institution::create([
            'name' => 'Restatement Partner', 'address' => 'Kampala',
            'phone' => '256700009994', 'email' => 'restatement-ecl-test@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $service = app(InstitutionalEclService::class);
        $date = now()->toDateString();
        $policy = $service->stagePolicy($institution->id, [
            'version' => 'restatement-test-v1', 'effective_from' => $date,
            'currency' => 'UGX', 'stage2_dpd' => 30, 'stage3_dpd' => 90,
            'discount_bps' => 0, 'methodology_reference' => 'REVIEWED-TEST-RISK',
            'scenarios' => $this->scenarios(),
        ], $maker);
        $service->approvePolicy($policy, $checker);
        $source = $service->stageExternal($institution->id, [
            'external_loan_reference' => 'EXT-CORR-1', 'as_of_date' => $date,
            'currency' => 'UGX', 'principal_outstanding_minor' => 100000,
            'days_past_due' => 35, 'sicr' => true, 'credit_impaired' => false,
            'contractual_maturity_date' => now()->addYear()->toDateString(),
            'source_evidence' => 'INITIAL-SOURCE-1',
        ], $maker);
        $service->approveExternal($source, $checker);
        $originalRun = $service->build($institution->id, $date, 1, 100000, 'FIRST-SIGNED-MIS', $maker);
        $service->approveRun($originalRun, $checker);
        $service->postRun($originalRun, $checker);
        $originalHash = $service->details($originalRun)['run']->content_hash;
        $ledgerCount = DB::table('ledger_transactions')->count();

        $correction = $service->stageSourceCorrection($source, [
            'principal_outstanding_minor' => 80000, 'days_past_due' => 100,
            'sicr' => true, 'credit_impaired' => true,
            'contractual_maturity_date' => now()->addYear()->toDateString(),
            'source_evidence' => 'CORRECTED-MIS-1',
            'reason' => 'Independent source investigation confirmed revised loan balance.',
        ], $maker);
        $this->assertSame(100000, (int) DB::table('institution_ecl_external_snapshots')->find($source)->principal_outstanding_minor);
        try {
            $service->build($institution->id, $date, 1, 80000, 'SECOND-SIGNED-MIS', $maker, $originalRun, 'Revised MIS control');
            $this->fail('A pending correction must block restatement.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('pending source correction', $e->getMessage());
        }
        $service->approveSourceCorrection($correction, $checker);
        $revisedRun = $service->build(
            $institution->id, $date, 1, 80000, 'REVISED-MIS-SIGNED',
            $maker, $originalRun, 'Corrected original MIS source balance after independent review.'
        );
        $this->assertNotEquals($originalHash, $service->details($revisedRun)['run']->content_hash);
        $service->approveRun($revisedRun, $checker);
        $service->postRun($revisedRun, $checker);
        $this->assertSame('superseded', $service->details($originalRun)['run']->status);
        $this->assertSame('finalised', $service->details($revisedRun)['run']->status);
        $this->assertSame($ledgerCount, DB::table('ledger_transactions')->count());
        $this->assertSame(100000, (int) DB::table('institution_ecl_external_snapshots')->find($source)->principal_outstanding_minor);
        $this->assertSame(80000, (int) $service->details($revisedRun)['items'][0]->gross_exposure_minor);
    }

    public function test_delegated_operations_cannot_list_another_institutions_financial_provisions(): void
    {
        $own = Institution::create([
            'name' => 'Authorised Loan Partner', 'address' => 'Kampala',
            'phone' => '256700009995', 'email' => 'owned-ledger-ecl@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $other = Institution::create([
            'name' => 'Independent External Partner', 'address' => 'Jinja',
            'phone' => '256700009996', 'email' => 'other-ledger-ecl@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $user = User::factory()->create([
            'role' => User::ROLE_OPERATIONS, 'institution_id' => $own->id,
        ]);
        $user->forceFill(['can_manage_platform_credit' => true])->save();
        $this->actingAs($user)->getJson('/api/admin/institutional-ecl?institution_id='.$own->id)->assertOk();
        $this->actingAs($user)->getJson('/api/admin/institutional-ecl?institution_id='.$other->id)->assertForbidden();
        $this->actingAs($user)->postJson('/api/admin/institutional-ecl/policies', [
            'institution_id' => $other->id, 'version' => 'unauthorised-test',
            'effective_from' => now()->toDateString(), 'currency' => 'UGX',
            'stage2_dpd' => 30, 'stage3_dpd' => 90, 'discount_bps' => 0,
            'methodology_reference' => 'SCOPE-TEST', 'scenarios' => $this->scenarios(),
        ])->assertForbidden();
        $this->assertSame(0, DB::table('institution_ecl_policies')->where('institution_id', $other->id)->count());
    }

    public function test_policy_approval_cannot_be_replayed(): void
    {
        $institution = Institution::create([
            'name' => 'Test Institution', 'address' => 'Kampala',
            'phone' => '256700009992', 'email' => 'institution-ecl-test@example.com',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-NOT-LIVE',
            'regulator_code' => 'TEST',
        ]);
        $a = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $b = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $engine = app(InstitutionalEclService::class);
        $id = $engine->stagePolicy($institution->id, [
            'version' => 'test-once', 'effective_from' => now()->toDateString(),
            'currency' => 'UGX', 'stage2_dpd' => 30, 'stage3_dpd' => 90,
            'discount_bps' => 0, 'methodology_reference' => 'TEST',
            'scenarios' => $this->scenarios(),
        ], $a);
        $engine->approvePolicy($id, $b);
        $this->expectException(InvalidArgumentException::class);
        $engine->approvePolicy($id, $b);
    }
}
