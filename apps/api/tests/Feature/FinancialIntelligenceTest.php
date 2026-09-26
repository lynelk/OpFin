<?php

namespace Tests\Feature;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use App\Services\FinancialIntelligence\PortfolioEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;
    private User $checker;
    private FinancialSpace $space;
    private int $source;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-26 08:00:00');
        config(['financial_intelligence.enabled' => true]);
        Storage::fake('local');
        $this->maker = User::factory()->create();
        $this->checker = User::factory()->create();
        $this->space = $this->spaceWithMembers([$this->maker, $this->checker]);
        $this->base = '/api/financial-spaces/'.$this->space->id.'/intelligence';
        $this->actingAs($this->maker, 'sanctum');
        $this->source = (int) $this->postJson($this->base.'/sources', ['name' => 'Synthetic MIS', 'population' => 'Whole book', 'country' => 'UG',
            'lawful_basis_reference' => 'synthetic-processing-agreement'])->assertCreated()->json('data.source_id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spaceWithMembers(array $users, string $type = 'sacco'): FinancialSpace
    {
        $space = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => $type, 'name' => 'Synthetic test institution',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        foreach ($users as $user) {
            FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        }
        $plan = DB::table('opfin_plans')->insertGetId(['code' => 'fi-test-'.Str::random(12), 'name' => 'Synthetic FI test plan', 'price_minor' => 0,
            'currency' => 'UGX', 'billing_period' => 'monthly', 'status' => 'active', 'metadata' => json_encode(['entitlements' => ['financial_intelligence']]), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('financial_space_entitlements')->insert(['financial_space_id' => $space->id, 'opfin_plan_id' => $plan, 'entitlement_key' => 'financial_intelligence',
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => null, 'created_at' => now(), 'updated_at' => now()]);
        return $space;
    }

    private function data(int $dpd = 31): array
    {
        return ['schema_version' => PortfolioEngine::VERSION, 'as_of' => '2026-09-25', 'source_system' => 'Synthetic MIS', 'population' => 'Whole book',
            'expected_loan_count' => 1, 'control_totals_minor' => ['UGX' => 100000], 'loans' => [['loan_ref' => 'SYN-L-1', 'borrower_ref' => 'SYN-B-1',
                'currency' => 'UGX', 'principal_outstanding_minor' => 100000, 'original_principal_minor' => 100000, 'originated_on' => '2026-01-01',
                'product' => 'Working capital', 'regulatory_npl' => false, 'regulatory_classification_ref' => 'synthetic-policy',
                'instalments' => [['instalment_ref' => 'SYN-I-1', 'due_on' => Carbon::parse('2026-09-25')->subDays($dpd)->toDateString(),
                    'principal_due_minor' => 100000, 'interest_due_minor' => 0, 'principal_paid_minor' => 0, 'interest_paid_minor' => 0]]]], 'financials' => []];
    }

    private function stage(string $key = 'synthetic-import-one'): int
    {
        $this->actingAs($this->maker, 'sanctum');
        return (int) $this->postJson($this->base.'/sources/'.$this->source.'/imports', ['portfolio' => $this->data()], ['Idempotency-Key' => $key])
            ->assertCreated()->json('data.id');
    }

    private function publish(): int
    {
        $id = $this->stage();
        $this->actingAs($this->checker, 'sanctum')->postJson($this->base.'/imports/'.$id.'/review', ['decision' => 'published', 'reason' => 'Source count and totals independently checked.'])->assertOk();
        return $id;
    }

    public function test_staged_data_are_not_published_and_are_encrypted(): void
    {
        $id = $this->stage();
        $this->assertNull(DB::table('fi_sources')->find($this->source)->current_import_id);
        $row = DB::table('fi_imports')->find($id);
        $this->assertStringNotContainsString('SYN-B-1', $row->payload_cipher);
        $this->assertStringContainsString('SYN-B-1', Crypt::decryptString($row->payload_cipher));
        $this->getJson($this->base)->assertOk()->assertJsonPath('data.sources.0.analysis', null);
    }

    public function test_import_replay_is_bound_to_content(): void
    {
        $id = $this->stage();
        $this->assertSame($id, $this->stage());
        $data = $this->data(); $data['as_of'] = '2026-09-24';
        $this->postJson($this->base.'/sources/'.$this->source.'/imports', ['portfolio' => $data], ['Idempotency-Key' => 'synthetic-import-one'])->assertConflict();
        $this->assertSame(1, DB::table('fi_imports')->count());
    }

    public function test_control_total_mismatch_does_not_create_an_import(): void
    {
        $data = $this->data(); $data['control_totals_minor']['UGX']++;
        $this->postJson($this->base.'/sources/'.$this->source.'/imports', ['portfolio' => $data], ['Idempotency-Key' => 'bad-control'])->assertUnprocessable();
        $this->assertSame(0, DB::table('fi_imports')->count());
    }

    public function test_independent_review_and_replay_do_not_duplicate_cases(): void
    {
        $id = $this->stage();
        $body = ['decision' => 'published', 'reason' => 'Source checked'];
        $this->postJson($this->base.'/imports/'.$id.'/review', $body)->assertForbidden();
        $this->actingAs($this->checker, 'sanctum')->postJson($this->base.'/imports/'.$id.'/review', $body)->assertOk();
        $this->postJson($this->base.'/imports/'.$id.'/review', $body)->assertOk();
        $this->assertSame(1, DB::table('fi_publications')->count());
        $this->assertSame(1, DB::table('fi_cases')->count());
        $this->assertSame(1, DB::table('fi_case_events')->count());
        $this->getJson($this->base)->assertOk()->assertJsonPath('data.sources.0.analysis.currency_metrics.UGX.par30_bps', 10000);
    }

    public function test_cross_space_imports_cases_and_reports_are_denied(): void
    {
        $id = $this->publish();
        $report = $this->postJson($this->base.'/reports', ['import_id' => $id])->assertCreated()->json('data.report_id');
        $other = $this->spaceWithMembers([$this->checker]);
        $base = '/api/financial-spaces/'.$other->id.'/intelligence';
        $this->getJson($base.'/imports/'.$id)->assertNotFound();
        $this->getJson($base.'/reports/'.$report)->assertNotFound();
        $this->postJson($base.'/cases/1/events', ['action' => 'note', 'note' => 'Denied', 'expected_version' => 1], ['Idempotency-Key' => 'denied'])->assertNotFound();
    }

    public function test_soft_deleted_or_suspended_members_cannot_read_or_write(): void
    {
        $id = $this->publish();
        FinancialSpaceMembership::query()->where('financial_space_id', $this->space->id)->where('user_id', $this->checker->id)->delete();
        $this->getJson($this->base.'/imports/'.$id)->assertForbidden();
        $this->postJson($this->base.'/reports', ['import_id' => $id])->assertForbidden();
    }

    public function test_platform_admin_is_not_a_cross_institution_backdoor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $this->actingAs($admin, 'sanctum')->getJson($this->base)->assertForbidden();
    }

    public function test_expired_entitlement_denies_analytics(): void
    {
        DB::table('financial_space_entitlements')->where('financial_space_id', $this->space->id)->update(['ends_at' => now()->subMinute()]);
        $this->getJson($this->base)->assertForbidden();
    }

    public function test_board_has_aggregates_but_no_borrower_records(): void
    {
        $id = $this->publish();
        $board = User::factory()->create();
        FinancialSpaceMembership::query()->create(['financial_space_id' => $this->space->id, 'user_id' => $board->id, 'role' => 'member', 'status' => 'active']);
        $this->putJson($this->base.'/grants', ['user_id' => $board->id, 'role' => 'board', 'expires_at' => '2026-10-01'])->assertOk();
        $this->actingAs($board, 'sanctum')->getJson($this->base)->assertOk()->assertDontSee('SYN-B-1');
        $this->getJson($this->base.'/imports/'.$id)->assertForbidden();
        $this->getJson($this->base.'/cases')->assertForbidden();
    }

    public function test_case_version_conflicts_and_idempotency_are_explicit(): void
    {
        $this->publish();
        $case = DB::table('fi_cases')->first();
        $body = ['action' => 'start', 'expected_version' => 1, 'note' => 'Reviewing source evidence'];
        $this->postJson($this->base.'/cases/'.$case->id.'/events', $body, ['Idempotency-Key' => 'event-one'])->assertCreated()->assertJsonPath('data.version', 2);
        $this->postJson($this->base.'/cases/'.$case->id.'/events', $body, ['Idempotency-Key' => 'event-one'])->assertCreated()->assertJsonPath('data.replayed', true);
        $this->postJson($this->base.'/cases/'.$case->id.'/events', $body, ['Idempotency-Key' => 'event-two'])->assertConflict();
        $this->assertSame(2, DB::table('fi_case_events')->count());
    }

    public function test_frozen_report_survives_space_renaming(): void
    {
        $id = $this->publish();
        $r = $this->postJson($this->base.'/reports', ['import_id' => $id])->assertCreated()->json('data');
        $before = $this->getJson($this->base.'/reports/'.$r['report_id'])->assertOk()->json('data');
        $this->space->update(['name' => 'Changed display name']);
        $after = $this->getJson($this->base.'/reports/'.$r['report_id'])->assertOk()->json('data');
        $this->assertSame($before, $after);
        $this->getJson($this->base.'/reports/'.$r['report_id'].'/csv')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_report_evidence_cannot_be_updated(): void
    {
        $id = $this->publish();
        $report = $this->postJson($this->base.'/reports', ['import_id' => $id])->assertCreated()->json('data.report_id');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('fi_reports')->where('id', $report)->update(['content_hash' => str_repeat('0', 64)]);
    }

    public function test_network_sharing_is_report_scoped_revocable_and_suppresses_small_counts(): void
    {
        $id = $this->publish();
        $report = $this->postJson($this->base.'/reports', ['import_id' => $id])->assertCreated()->json('data.report_id');
        $recipient = $this->spaceWithMembers([$this->checker]);
        $body = ['recipient_space_id' => $recipient->id, 'expires_at' => '2026-10-10'];
        $this->putJson($this->base.'/reports/'.$report.'/share', $body)->assertOk();
        $path = '/api/financial-spaces/'.$recipient->id.'/intelligence/network';
        $this->getJson($path)->assertOk()->assertJsonPath('data.items.0.report.analysis.currency_metrics.UGX.suppressed', true)->assertDontSee('SYN-B-1');
        $this->putJson($this->base.'/reports/'.$report.'/share', [...$body, 'revoke' => true])->assertOk();
        $this->getJson($path)->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_statements_require_eligible_issuer_and_never_gain_authenticity_from_arithmetic(): void
    {
        $id = $this->issuer();
        $csv = "date,reference,description,direction,amount_minor,balance_minor\n2026-09-02,R1,Receipt,credit,500,1500\n";
        $response = $this->post($this->base.'/statements', ['statement_file' => UploadedFile::fake()->createWithContent('statement.csv', $csv),
            'issuer_version_id' => $id, 'currency' => 'UGX', 'period_start' => '2026-09-01', 'period_end' => '2026-09-25',
            'opening_balance_minor' => 1000, 'closing_balance_minor' => 1500, 'account_reference' => 'SYN-ACCOUNT-1',
            'authority_reference' => 'synthetic-authority', 'authority_confirmed' => '1', 'authority_expires_at' => '2026-10-01', 'purpose' => 'financial_analysis'],
            ['Accept' => 'application/json', 'Idempotency-Key' => 'statement-one'])->assertCreated();
        $statement = $response->json('data.id');
        $this->getJson($this->base.'/statements/'.$statement)->assertOk()->assertJsonPath('data.source_authenticity', 'unconfirmed')->assertJsonPath('data.credit_decision_eligible', false);
        $this->deleteJson($this->base.'/statements/'.$statement.'/permission')->assertOk();
        $this->getJson($this->base.'/statements/'.$statement)->assertForbidden();
    }

    public function test_personal_spaces_do_not_receive_institutional_portfolio_access(): void
    {
        $personal = $this->spaceWithMembers([$this->maker], 'personal');
        $this->getJson('/api/financial-spaces/'.$personal->id.'/intelligence')->assertForbidden();
        $this->getJson('/api/financial-spaces/'.$personal->id.'/intelligence/issuers')->assertOk();
    }

    private function issuer(): int
    {
        return DB::table('fi_issuer_versions')->insertGetId(['public_id' => (string) Str::uuid(), 'issuer_code' => 'SYNTHETIC_ONLY', 'legal_name' => 'Synthetic test issuer, not a real licence',
            'country' => 'UG', 'product_type' => 'bank', 'regulator' => 'Synthetic authority', 'licence_reference' => 'TEST', 'evidence_reference' => 'fixture-only',
            'valid_from' => '2020-01-01', 'valid_until' => '2026-12-31', 'review_due_on' => '2026-12-31', 'proposed_by' => $this->maker->id,
            'approved_by' => $this->checker->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
