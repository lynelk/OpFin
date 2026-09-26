<?php

namespace Tests\Feature;

use App\Models\ClubAsset;
use App\Models\ClubBook;
use App\Models\ClubMember;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use App\Services\ClubAccounting\ClubInstructions;
use App\Services\ClubAccounting\ClubIntegrity;
use App\Services\ClubAccounting\ClubLedger;
use App\Services\ClubAccounting\ClubReports;
use App\Services\ClubAccounting\ClubSchedules;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $checker;
    private User $member;
    private FinancialSpace $space;
    private FinancialSpaceTreasuryAccount $treasury;
    private ClubBook $book;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 25)->setTime(10, 0));
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['role' => 'customer']);
        $this->checker = User::factory()->create(['role' => 'customer']);
        $this->member = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($this->owner);
        $spaceId = $this->postJson('/api/financial-spaces', ['type' => 'investment_club', 'name' => 'Synthetic Member Accounting Club', 'country' => 'UG', 'currency' => 'UGX'])
            ->assertCreated()->json('data.space.id');
        $this->space = FinancialSpace::findOrFail($spaceId);
        foreach ([[$this->checker, 'treasurer'], [$this->member, 'member']] as [$user, $role]) {
            FinancialSpaceMembership::create(['financial_space_id' => $spaceId, 'user_id' => $user->id, 'role' => $role,
                'status' => 'active', 'joined_at' => now(), 'approved_at' => now()]);
        }
        $accountId = $this->postJson("/api/financial-spaces/{$spaceId}/treasury/accounts", [
            'account_name' => 'Synthetic Club Bank', 'account_type' => 'bank', 'currency' => 'UGX',
            'opening_balance_minor' => 1000000, 'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');
        $this->treasury = FinancialSpaceTreasuryAccount::findOrFail($accountId);
        $bookId = $this->postJson("/api/financial-spaces/{$spaceId}/accounting/books", ['currency' => 'UGX',
            'ownership_model' => 'unitised', 'cutover_date' => '2026-09-01', 'initial_unit_price_minor' => 1000, 'valuation_max_age_days' => 30])
            ->assertCreated()->json('data.book.id');
        $this->book = ClubBook::findOrFail($bookId);
        $this->base = "/api/financial-spaces/{$spaceId}/accounting/books/{$bookId}";
    }

    private function submit(string $type, array $payload, string $date = '2026-09-01', ?string $key = null): array
    {
        Sanctum::actingAs($this->owner);
        return $this->postJson($this->base.'/instructions', ['type' => $type, 'business_date' => $date,
            'idempotency_key' => $key ?? (string) Str::uuid(), 'payload' => $payload + ['evidence_reference' => 'SYNTHETIC-'.Str::upper(Str::random(12))]])
            ->assertCreated()->json('data.instruction');
    }

    private function approve(array $instruction): array
    {
        Sanctum::actingAs($this->checker);
        return $this->postJson($this->base.'/instructions/'.$instruction['id'].'/approve', ['payload_hash' => $instruction['payload_hash']])
            ->assertOk()->assertJsonPath('data.instruction.status', 'approved')->json('data.instruction');
    }

    private function applyInstruction(string $type, array $payload, string $date = '2026-09-01'): array
    {
        return $this->approve($this->submit($type, $payload, $date));
    }

    private function opening(): void
    {
        $this->applyInstruction('opening', ['members' => [['user_id' => $this->owner->id, 'capital_minor' => 1000000, 'units_micro' => 1000000000]],
            'balances' => [['account_code' => 'CASH-'.$this->treasury->id, 'direction' => 'debit', 'amount_minor' => 1000000]]]);
    }

    private function assertApprovalRejected(array $item): void
    {
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', ['payload_hash' => $item['payload_hash']])->assertUnprocessable();
    }

    public function test_book_setup_and_opening_require_independent_approval_and_source_balances(): void
    {
        $this->assertSame('draft', $this->book->status);
        $item = $this->submit('opening', ['members' => [['user_id' => $this->owner->id, 'capital_minor' => 1000000, 'units_micro' => 1000000000]],
            'balances' => [['account_code' => 'CASH-'.$this->treasury->id, 'direction' => 'debit', 'amount_minor' => 1000000]]]);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', ['payload_hash' => $item['payload_hash']])->assertForbidden();
        $this->assertSame(0, DB::table('club_journals')->count());
        $this->approve($item);
        $this->assertSame('active', $this->book->fresh()->status);
        $this->assertDatabaseHas('club_members', ['book_id' => $this->book->id, 'user_id' => $this->owner->id, 'capital_minor' => 1000000]);
        $this->assertTrue(app(ClubIntegrity::class)->check($this->book->fresh())['integrity_passed']);
        Http::assertNothingSent();
    }

    public function test_complete_contribution_investment_distribution_and_redemption_lifecycle(): void
    {
        $corporate = DB::table('ledger_transactions')->count();
        $this->opening();
        $this->applyInstruction('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 500000, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        $buy = $this->applyInstruction('asset_acquisition', ['name' => 'Synthetic Fund Holding', 'asset_class' => 'unit_trust',
            'quantity_micro' => 100000000, 'amount_minor' => 500000, 'fee_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-03');
        $assetId = $buy['result']['asset_id'];
        $this->applyInstruction('asset_valuation', ['asset_id' => $assetId, 'carrying_value_minor' => 600000], '2026-09-04');
        $subscription = $this->applyInstruction('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 159900,
            'treasury_account_id' => $this->treasury->id], '2026-09-05');
        $this->assertSame(150000000, $subscription['result']['units_issued_micro']);
        $distribution = $this->applyInstruction('distribution_declare', ['amount_minor' => 99000], '2026-09-06');
        $this->assertSame(60000, $distribution['result']['allocations_minor'][$this->owner->id]);
        $this->assertSame(39000, $distribution['result']['allocations_minor'][$this->member->id]);
        $this->applyInstruction('distribution_pay', ['distribution_id' => $distribution['result']['distribution_id'],
            'member_user_id' => $this->owner->id, 'amount_minor' => 30000, 'withholding_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-07');
        $redemption = $this->applyInstruction('redemption', ['member_user_id' => $this->member->id, 'units_micro' => 65000000,
            'treasury_account_id' => $this->treasury->id], '2026-09-08');
        $this->assertSame(65390, $redemption['result']['gross_minor']);
        $sale = $this->applyInstruction('asset_disposal', ['asset_id' => $assetId, 'quantity_micro' => 50000000,
            'amount_minor' => 320000, 'fee_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-09');
        $this->assertSame(250500, $sale['result']['cost_released_minor']);
        $this->assertSame(300000, $sale['result']['carrying_released_minor']);
        $this->assertSame(69500, $sale['result']['realised_before_fee_minor']);
        $asset = ClubAsset::findOrFail($assetId);
        $this->assertSame(50000000, $asset->quantity_micro);
        $this->assertSame(250500, $asset->cost_minor);
        $this->assertSame(300000, $asset->carrying_value_minor);
        $book = $this->book->fresh();
        $this->assertSame([], app(ClubIntegrity::class)->check($book)['issues']);
        $trial = app(ClubLedger::class)->trialBalance($book);
        $this->assertTrue($trial['balanced']);
        $this->assertSame(0, $trial['position']['accounting_equation_variance_minor']);
        $this->assertSame($this->treasury->fresh()->current_balance_minor, app(ClubLedger::class)->debitBalance($book, 'CASH-'.$this->treasury->id));
        $this->assertSame($corporate, DB::table('ledger_transactions')->count());
        Http::assertNothingSent();
    }

    public function test_submission_and_approval_replay_have_exactly_once_effects(): void
    {
        $this->opening(); $key = (string) Str::uuid();
        $payload = ['member_user_id' => $this->member->id, 'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id, 'evidence_reference' => 'SYNTHETIC-REPLAY'];
        $one = $this->submit('contribution', $payload, '2026-09-02', $key);
        $two = $this->submit('contribution', $payload, '2026-09-02', $key);
        $this->assertSame($one['id'], $two['id']); $this->approve($one);
        $journals = DB::table('club_journals')->count(); $cash = DB::table('financial_space_transactions')->count();
        $this->approve($one);
        $this->assertSame($journals, DB::table('club_journals')->count());
        $this->assertSame($cash, DB::table('financial_space_transactions')->count());
        Sanctum::actingAs($this->owner); $payload['amount_minor'] = 1001;
        $this->postJson($this->base.'/instructions', ['type' => 'contribution', 'business_date' => '2026-09-02', 'idempotency_key' => $key, 'payload' => $payload])->assertUnprocessable();
    }

    public function test_approval_hash_mismatch_preserves_pending_state(): void
    {
        $this->opening();
        $item = $this->submit('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 100, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', ['payload_hash' => str_repeat('0', 64)])->assertUnprocessable();
        $this->assertDatabaseHas('club_instructions', ['id' => $item['id'], 'status' => 'pending']);
        $this->assertSame(1000000, $this->treasury->fresh()->current_balance_minor);
    }

    public function test_preview_rolls_back_all_accounting_and_cashbook_effects(): void
    {
        $this->opening();
        $item = $this->submit('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 100, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        $before = DB::table('club_journals')->count();
        $this->postJson($this->base.'/instructions/'.$item['id'].'/preview')->assertOk()->assertJsonPath('data.simulation_only', true)->assertJsonPath('data.committed', false);
        $this->assertSame($before, DB::table('club_journals')->count());
        $this->assertSame(1000000, $this->treasury->fresh()->current_balance_minor);
        $this->assertDatabaseMissing('club_members', ['book_id' => $this->book->id, 'user_id' => $this->member->id]);
    }

    public function test_opening_cash_mismatch_rolls_back_all_created_positions(): void
    {
        $item = $this->submit('opening', ['members' => [['user_id' => $this->owner->id, 'capital_minor' => 1100000, 'units_micro' => 1100000000]],
            'balances' => [['account_code' => 'CASH-'.$this->treasury->id, 'direction' => 'debit', 'amount_minor' => 1100000]]]);
        $this->assertApprovalRejected($item);
        $this->assertSame('draft', $this->book->fresh()->status);
        $this->assertSame(0, DB::table('club_members')->count()); $this->assertSame(0, DB::table('club_journals')->count());
    }

    public function test_wrong_space_and_non_officer_reads_are_denied(): void
    {
        $this->opening(); Sanctum::actingAs($this->member);
        $this->getJson($this->base.'/journals')->assertForbidden();
        $this->getJson($this->base.'/report?period_start=2026-09-01&period_end=2026-09-25')->assertForbidden();
        $this->getJson('/api/financial-spaces/'.($this->space->id + 1000).'/accounting/books/'.$this->book->id.'/integrity')->assertNotFound();
    }

    public function test_removed_checker_cannot_approve_existing_instructions(): void
    {
        $this->opening(); $item = $this->submit('capital_call', ['member_user_ids' => [$this->member->id], 'amount_minor' => 100, 'due_date' => '2026-10-01']);
        DB::table('financial_space_memberships')->where('financial_space_id', $this->space->id)->where('user_id', $this->checker->id)->update(['status' => 'inactive']);
        Sanctum::actingAs($this->checker);
        $this->postJson($this->base.'/instructions/'.$item['id'].'/approve', ['payload_hash' => $item['payload_hash']])->assertForbidden();
        $this->assertSame(0, DB::table('club_capital_calls')->count());
    }

    public function test_capital_calls_are_not_loan_receivables_and_partial_contributions_reconcile(): void
    {
        $this->opening();
        $call = $this->applyInstruction('capital_call', ['member_user_ids' => [$this->member->id], 'amount_minor' => 3000, 'due_date' => '2026-09-10']);
        $id = $call['result']['capital_call_ids'][0]; $this->assertSame(1, DB::table('club_journals')->count());
        foreach ([[1000, '2026-09-02'], [2000, '2026-09-03']] as [$amount, $date]) {
            $this->applyInstruction('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => $amount,
                'capital_call_id' => $id, 'treasury_account_id' => $this->treasury->id], $date);
        }
        $this->assertDatabaseHas('club_capital_calls', ['id' => $id, 'paid_minor' => 3000, 'status' => 'paid']);
    }

    public function test_monthly_generation_is_idempotent_and_clamps_the_due_day(): void
    {
        $this->opening();
        $this->applyInstruction('contribution_plan', ['member_user_id' => $this->member->id, 'amount_minor' => 2500,
            'first_due_month' => '2026-09', 'due_day' => 31, 'end_date' => '2026-12-31']);
        $this->travelTo(now()->setDate(2026, 11, 1)); $schedules = app(ClubSchedules::class);
        for ($i = 0; $i < 3; $i++) { $schedules->generateDueCalls(); }
        $this->assertSame(['2026-09-30', '2026-10-31'], DB::table('club_capital_calls')->orderBy('due_date')->pluck('due_date')->all());
        $this->assertSame(1, DB::table('club_journals')->count()); $this->assertSame(1000000, $this->treasury->fresh()->current_balance_minor);
        Http::assertNothingSent();
    }

    public function test_unchanged_valuation_and_stock_split_do_not_invent_zero_journals(): void
    {
        $this->opening();
        $buy = $this->applyInstruction('asset_acquisition', ['name' => 'Synthetic Shares', 'asset_class' => 'equity',
            'quantity_micro' => 10000000, 'amount_minor' => 100000, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        $id = $buy['result']['asset_id']; $count = DB::table('club_journals')->count();
        $this->applyInstruction('asset_valuation', ['asset_id' => $id, 'carrying_value_minor' => 100000], '2026-09-03');
        $this->applyInstruction('asset_split', ['asset_id' => $id, 'numerator' => 2, 'denominator' => 1], '2026-09-04');
        $this->assertSame($count, DB::table('club_journals')->count());
        $this->assertDatabaseHas('club_assets', ['id' => $id, 'quantity_micro' => 20000000, 'cost_minor' => 100000, 'carrying_value_minor' => 100000]);
    }

    public function test_stale_valuation_blocks_new_ownership_until_refreshed(): void
    {
        $this->opening();
        $this->applyInstruction('asset_acquisition', ['name' => 'Synthetic Holding', 'asset_class' => 'equity',
            'quantity_micro' => 1000000, 'amount_minor' => 100000, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        $this->travelTo(now()->setDate(2026, 11, 1));
        $this->assertApprovalRejected($this->submit('contribution', ['member_user_id' => $this->member->id,
            'amount_minor' => 100, 'treasury_account_id' => $this->treasury->id], '2026-11-01'));
        $this->assertSame(900000, $this->treasury->fresh()->current_balance_minor);
    }

    public function test_existing_cashbook_receipt_is_classified_once_without_duplicate_cash(): void
    {
        $this->opening(); Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/financial-spaces/'.$this->space->id.'/treasury/accounts/'.$this->treasury->id.'/transactions', [
            'transaction_date' => '2026-09-02', 'direction' => 'credit', 'amount_minor' => 1000,
            'description' => 'Synthetic bank interest', 'transaction_reference' => 'SYNTHETIC-INTEREST'])->assertCreated()->json('data.transaction.id');
        $payload = ['direction' => 'credit', 'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id,
            'treasury_transaction_id' => $id, 'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 1000]]];
        $this->applyInstruction('cash_entry', $payload, '2026-09-02');
        $this->assertSame(1001000, $this->treasury->fresh()->current_balance_minor);
        $this->assertSame(1, DB::table('financial_space_transactions')->count());
        $this->assertApprovalRejected($this->submit('cash_entry', $payload, '2026-09-02'));
        $this->assertSame(1, DB::table('financial_space_transactions')->count());
    }

    public function test_unclassified_earlier_cash_prevents_an_inaccurate_subscription_price(): void
    {
        $this->opening(); Sanctum::actingAs($this->owner);
        $this->postJson('/api/financial-spaces/'.$this->space->id.'/treasury/accounts/'.$this->treasury->id.'/transactions', [
            'transaction_date' => '2026-09-02', 'direction' => 'credit', 'amount_minor' => 1000, 'description' => 'Unclassified receipt'])->assertCreated();
        $this->assertApprovalRejected($this->submit('contribution', ['member_user_id' => $this->member->id,
            'amount_minor' => 100, 'treasury_account_id' => $this->treasury->id], '2026-09-03'));
    }

    public function test_closed_period_rejects_backdated_financial_instructions(): void
    {
        $this->opening(); $this->applyInstruction('close_period', ['closed_through' => '2026-09-10'], '2026-09-11');
        Sanctum::actingAs($this->owner);
        $this->postJson($this->base.'/instructions', ['type' => 'contribution', 'business_date' => '2026-09-05',
            'idempotency_key' => (string) Str::uuid(), 'payload' => ['member_user_id' => $this->member->id,
                'amount_minor' => 1, 'treasury_account_id' => $this->treasury->id, 'evidence_reference' => 'SYNTHETIC-CLOSED']])->assertUnprocessable();
        $this->assertDatabaseHas('club_period_closures', ['book_id' => $this->book->id, 'closed_through' => '2026-09-10']);
    }

    public function test_ordinary_reversal_appends_new_evidence_and_cannot_be_repeated(): void
    {
        $this->opening();
        $income = $this->applyInstruction('cash_entry', ['direction' => 'credit', 'amount_minor' => 1000,
            'treasury_account_id' => $this->treasury->id, 'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 1000]]], '2026-09-02');
        $id = $income['result']['journal_id']; $hash = DB::table('club_journals')->where('id', $id)->value('content_hash');
        $this->applyInstruction('reverse_journal', ['original_journal_id' => $id, 'description' => 'Synthetic correction of duplicate income'], '2026-09-03');
        $this->assertSame(1000000, $this->treasury->fresh()->current_balance_minor);
        $this->assertSame($hash, DB::table('club_journals')->where('id', $id)->value('content_hash'));
        $this->assertApprovalRejected($this->submit('reverse_journal', ['original_journal_id' => $id, 'description' => 'Duplicate correction must not post'], '2026-09-04'));
    }

    public function test_statements_are_frozen_and_same_request_returns_the_same_issue(): void
    {
        $this->opening(); Sanctum::actingAs($this->owner);
        $input = ['period_start' => '2026-09-01', 'period_end' => '2026-09-25', 'idempotency_key' => 'SYNTHETIC-STATEMENT-1'];
        $one = $this->postJson($this->base.'/statements', $input)->assertCreated()->json('data.statement');
        $this->space->update(['name' => 'Changed after statement issue']);
        $two = $this->postJson($this->base.'/statements', $input)->assertCreated()->json('data.statement');
        $this->assertSame($one['id'], $two['id']); $this->assertSame('Synthetic Member Accounting Club', $two['snapshot']['space']['name']);
        $this->getJson($this->base.'/statements/'.$one['id'])->assertOk()->assertJsonPath('data.statement.content_hash', $one['content_hash']);
        $this->get($this->base.'/statements/'.$one['id'].'/html')->assertOk()->assertSee('Synthetic Member Accounting Club');
        $this->get($this->base.'/statements/'.$one['id'].'/csv')->assertOk();
    }

    public function test_former_member_retains_only_their_own_financial_history(): void
    {
        $this->opening(); $this->applyInstruction('contribution', ['member_user_id' => $this->member->id,
            'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-02');
        DB::table('financial_space_memberships')->where('financial_space_id', $this->space->id)->where('user_id', $this->member->id)->update(['status' => 'inactive']);
        Sanctum::actingAs($this->member);
        $this->getJson($this->base.'/report?period_start=2026-09-01&period_end=2026-09-25&member_user_id='.$this->member->id)
            ->assertOk()->assertJsonCount(1, 'data.report.member_positions')->assertJsonMissingPath('data.report.trial_balance');
        $this->getJson($this->base.'/report?period_start=2026-09-01&period_end=2026-09-25&member_user_id='.$this->owner->id)->assertForbidden();
        $this->getJson($this->base.'/catalogue')->assertForbidden();
    }

    public function test_historical_reports_exclude_later_member_activity(): void
    {
        $this->opening(); $this->applyInstruction('contribution', ['member_user_id' => $this->member->id,
            'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-15');
        $report = app(ClubReports::class)->report($this->book->fresh(), $this->owner, '2026-09-01', '2026-09-10');
        $this->assertCount(1, $report['member_positions']); $this->assertSame(1000000, $report['net_asset_value']['net_assets_minor']);
    }

    public function test_database_rejects_edits_to_posted_journals(): void
    {
        $this->opening(); $journal = DB::table('club_journals')->first();
        $this->expectException(QueryException::class);
        DB::table('club_journals')->where('id', $journal->id)->update(['description' => 'Tampered']);
    }

    public function test_multiple_opening_assets_use_one_balanced_journal(): void
    {
        $item = $this->applyInstruction('opening', [
            'members' => [['user_id' => $this->owner->id, 'capital_minor' => 1300000, 'units_micro' => 1300000000]],
            'balances' => [['account_code' => 'CASH-'.$this->treasury->id, 'direction' => 'debit', 'amount_minor' => 1000000]],
            'assets' => [
                ['name' => 'Synthetic Asset One', 'asset_class' => 'equity', 'quantity_micro' => 1000000, 'cost_minor' => 100000, 'carrying_value_minor' => 100000],
                ['name' => 'Synthetic Asset Two', 'asset_class' => 'unit_trust', 'quantity_micro' => 2000000, 'cost_minor' => 200000, 'carrying_value_minor' => 200000],
            ],
        ]);
        $this->assertSame(1300000, $item['result']['net_assets_minor']);
        $this->assertSame(2, DB::table('club_asset_movements')->count()); $this->assertSame(1, DB::table('club_journals')->count());
    }

    public function test_unknown_payload_fields_and_floating_point_money_are_rejected(): void
    {
        $this->opening(); Sanctum::actingAs($this->owner);
        foreach ([['amount_minor' => 12.5], ['amount_minor' => 12, 'status' => 'approved']] as $input) {
            $this->postJson($this->base.'/instructions', ['type' => 'contribution', 'business_date' => '2026-09-02',
                'idempotency_key' => (string) Str::uuid(), 'payload' => $input + ['member_user_id' => $this->member->id,
                    'treasury_account_id' => $this->treasury->id, 'evidence_reference' => 'SYNTHETIC-INVALID']])->assertUnprocessable();
        }
    }

    public function test_ownership_transfer_does_not_create_cash_or_new_capital(): void
    {
        $this->opening(); $before = $this->treasury->fresh()->current_balance_minor;
        $this->applyInstruction('ownership_transfer', ['from_user_id' => $this->owner->id, 'to_user_id' => $this->member->id, 'units_micro' => 250000000], '2026-09-02');
        $this->assertDatabaseHas('club_members', ['book_id' => $this->book->id, 'user_id' => $this->owner->id, 'capital_minor' => 750000, 'units_micro' => 750000000]);
        $this->assertDatabaseHas('club_members', ['book_id' => $this->book->id, 'user_id' => $this->member->id, 'capital_minor' => 250000, 'units_micro' => 250000000]);
        $this->assertSame($before, $this->treasury->fresh()->current_balance_minor); Http::assertNothingSent();
    }

    public function test_schema_exposes_exactly_the_implemented_instruction_types(): void
    {
        Sanctum::actingAs($this->owner);
        $types = $this->getJson('/api/accounting/club-schema')->assertOk()->json('data.operations');
        $this->assertSame(ClubInstructions::TYPES, array_column($types, 'type'));
    }
}
