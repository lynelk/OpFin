<?php

namespace Tests\Feature;

use App\Models\ClubBook;
use App\Models\ClubMember;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use App\Services\ClubAccounting\ClubBooks;
use App\Services\ClubAccounting\ClubInstructions;
use App\Services\ClubAccounting\ClubReports;
use App\Services\ClubAccounting\ClubSchedules;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClubAccountingReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $checker;
    private User $member;
    private FinancialSpace $space;
    private FinancialSpaceTreasuryAccount $treasury;
    private ClubBook $book;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 25)->setTime(10, 0));
        Http::preventStrayRequests();
        $this->owner = User::factory()->create(['role' => 'customer']);
        $this->checker = User::factory()->create(['role' => 'customer']);
        $this->member = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/financial-spaces', ['type' => 'investment_club', 'name' => 'Synthetic Review Club', 'country' => 'UG', 'currency' => 'UGX'])
            ->assertCreated()->json('data.space.id');
        $this->space = FinancialSpace::findOrFail($id);
        foreach ([[$this->checker, 'treasurer'], [$this->member, 'member']] as [$user, $role]) {
            FinancialSpaceMembership::create(['financial_space_id' => $id, 'user_id' => $user->id, 'role' => $role,
                'status' => 'active', 'joined_at' => now(), 'approved_at' => now()]);
        }
        $account = $this->postJson('/api/financial-spaces/'.$id.'/treasury/accounts', ['account_name' => 'Synthetic Bank',
            'account_type' => 'bank', 'currency' => 'UGX', 'opening_balance_minor' => 0, 'balance_as_of' => '2026-09-01'])
            ->assertCreated()->json('data.account.id');
        $this->treasury = FinancialSpaceTreasuryAccount::findOrFail($account);
        $this->book = app(ClubBooks::class)->create($this->space, $this->owner, ['currency' => 'UGX', 'ownership_model' => 'unitised',
            'cutover_date' => '2026-09-01', 'initial_unit_price_minor' => 1000, 'valuation_max_age_days' => 30]);
    }

    private function apply(string $type, array $payload, string $date = '2026-09-01'): array
    {
        $service = app(ClubInstructions::class);
        $item = $service->submit($this->book->fresh(), $this->owner, ['type' => $type, 'business_date' => $date,
            'idempotency_key' => (string) Str::uuid(), 'payload' => $payload + ['evidence_reference' => 'SYNTHETIC-'.Str::random(12)]]);
        return $service->approve($this->book->fresh(), $item, $this->checker, $item->payload_hash)->result;
    }

    private function fund(): void
    {
        $this->apply('opening', ['members' => [], 'balances' => []]);
        $this->apply('contribution', ['member_user_id' => $this->owner->id, 'amount_minor' => 1000000,
            'treasury_account_id' => $this->treasury->id], '2026-09-02');
    }

    private function source(string $direction, int $amount, string $date): int
    {
        Sanctum::actingAs($this->owner);
        return (int) $this->postJson('/api/financial-spaces/'.$this->space->id.'/treasury/accounts/'.$this->treasury->id.'/transactions', [
            'transaction_date' => $date, 'direction' => $direction, 'amount_minor' => $amount,
            'description' => 'Synthetic bank-derived source', 'source_type' => 'bank_import', 'source_reference' => (string) Str::uuid(),
        ])->assertCreated()->json('data.transaction.id');
    }

    public function test_empty_opening_and_first_subscription_create_no_invented_ownership(): void
    {
        $this->apply('opening', ['members' => [], 'balances' => []]);
        $this->assertSame(0, ClubMember::count());
        $this->assertSame(0, DB::table('club_journals')->count());
        $result = $this->apply('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 1000,
            'treasury_account_id' => $this->treasury->id], '2026-09-02');
        $this->assertSame(1000000, $result['units_issued_micro']);
        $this->assertSame('2026-09-01', $this->treasury->fresh()->balance_as_of->toDateString());
        Http::assertNothingSent();
    }

    public function test_book_replay_rejects_changed_unit_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ClubBooks::class)->create($this->space, $this->owner, ['currency' => 'UGX', 'ownership_model' => 'unitised',
            'cutover_date' => '2026-09-01', 'initial_unit_price_minor' => 2000, 'valuation_max_age_days' => 30]);
    }

    public function test_book_replay_rejects_changed_valuation_policy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(ClubBooks::class)->create($this->space, $this->owner, ['currency' => 'UGX', 'ownership_model' => 'unitised',
            'cutover_date' => '2026-09-01', 'initial_unit_price_minor' => 1000, 'valuation_max_age_days' => 60]);
    }

    public function test_later_distribution_payment_is_not_in_a_historical_report(): void
    {
        $this->fund();
        $this->apply('cash_entry', ['direction' => 'credit', 'amount_minor' => 10000, 'treasury_account_id' => $this->treasury->id,
            'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 10000]]], '2026-09-03');
        $distribution = $this->apply('distribution_declare', ['amount_minor' => 10000], '2026-09-04');
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->apply('distribution_pay', ['distribution_id' => $distribution['distribution_id'], 'member_user_id' => $this->owner->id,
            'amount_minor' => 10000, 'withholding_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-10-01');
        $report = app(ClubReports::class)->report($this->book->fresh(), $this->owner, '2026-09-01', '2026-09-30');
        $this->assertSame(0, $report['distributions'][0]['paid_to_date_minor']);
        $this->assertSame(10000, $report['distributions'][0]['outstanding_minor']);
        $current = app(ClubReports::class)->report($this->book->fresh(), $this->owner, '2026-09-01', '2026-10-05');
        $this->assertSame(10000, $current['distributions'][0]['paid_to_date_minor']);
    }

    public function test_later_capital_call_payment_and_cancellation_do_not_change_past_status(): void
    {
        $this->fund();
        $call = $this->apply('capital_call', ['member_user_ids' => [$this->member->id], 'amount_minor' => 1000, 'due_date' => '2026-09-10'], '2026-09-03');
        $other = $this->apply('capital_call', ['member_user_ids' => [$this->member->id], 'amount_minor' => 500, 'due_date' => '2026-09-10'], '2026-09-03');
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->apply('contribution', ['member_user_id' => $this->member->id, 'amount_minor' => 1000,
            'capital_call_id' => $call['capital_call_ids'][0], 'treasury_account_id' => $this->treasury->id], '2026-10-01');
        $this->apply('capital_call_cancel', ['capital_call_id' => $other['capital_call_ids'][0]], '2026-10-02');
        $past = app(ClubReports::class)->report($this->book->fresh(), $this->owner, '2026-09-01', '2026-09-30');
        $this->assertSame(['open', 'open'], array_column($past['capital_calls'], 'status'));
        $this->assertSame([0, 0], array_column($past['capital_calls'], 'paid_minor'));
        $present = app(ClubReports::class)->report($this->book->fresh(), $this->owner, '2026-09-01', '2026-10-05');
        $this->assertSame(['paid', 'cancelled'], array_column($present['capital_calls'], 'status'));
    }

    public function test_scheduled_calls_have_system_actor_audit_evidence(): void
    {
        $this->fund();
        $plan = $this->apply('contribution_plan', ['member_user_id' => $this->member->id, 'amount_minor' => 1000,
            'first_due_month' => '2026-09', 'due_day' => 10], '2026-09-03');
        app(ClubSchedules::class)->generateDueCalls();
        $this->assertDatabaseHas('audit_logs', ['event' => 'club.accounting.contribution_plan_advanced', 'actor_id' => null]);
        $this->assertDatabaseHas('club_capital_calls', ['plan_id' => $plan['contribution_plan_id'], 'due_date' => '2026-09-10']);
        DB::table('financial_space_memberships')->where('financial_space_id', $this->space->id)
            ->where('user_id', $this->member->id)->update(['status' => 'inactive']);
        $this->travelTo(now()->setDate(2026, 10, 15));
        app(ClubSchedules::class)->generateDueCalls();
        $this->assertDatabaseHas('audit_logs', ['event' => 'club.accounting.contribution_plan_closed', 'actor_id' => null]);
    }

    public function test_unposted_cashbook_debit_prevents_another_payment(): void
    {
        $this->fund();
        $this->source('debit', 900000, '2026-09-03');
        $this->expectException(InvalidArgumentException::class);
        $this->apply('asset_acquisition', ['name' => 'Synthetic Asset', 'asset_class' => 'equity', 'quantity_micro' => 1000000,
            'amount_minor' => 200000, 'treasury_account_id' => $this->treasury->id], '2026-09-04');
    }

    public function test_bank_source_cannot_be_cancelled_by_an_invented_cash_reversal(): void
    {
        $this->fund();
        $source = $this->source('credit', 1000, '2026-09-03');
        $posted = $this->apply('cash_entry', ['direction' => 'credit', 'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id,
            'treasury_transaction_id' => $source, 'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 1000]]], '2026-09-03');
        $this->expectException(InvalidArgumentException::class);
        $this->apply('reverse_journal', ['original_journal_id' => $posted['journal_id'], 'description' => 'Unproved reversal must fail'], '2026-09-04');
    }

    public function test_real_counter_transaction_allows_a_recorded_external_reversal_once(): void
    {
        $this->fund();
        $source = $this->source('credit', 1000, '2026-09-03');
        $posted = $this->apply('cash_entry', ['direction' => 'credit', 'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id,
            'treasury_transaction_id' => $source, 'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 1000]]], '2026-09-03');
        $counter = $this->source('debit', 1000, '2026-09-04');
        $this->apply('reverse_journal', ['original_journal_id' => $posted['journal_id'], 'treasury_transaction_id' => $counter,
            'description' => 'Actual bank reversal source matched'], '2026-09-04');
        $this->assertSame(1000000, $this->treasury->fresh()->current_balance_minor);
        $this->assertSame(3, DB::table('financial_space_transactions')->count());
        $this->assertSame(1, DB::table('club_treasury_posts')->where('treasury_transaction_id', $counter)->count());
    }

    public function test_distribution_record_date_cannot_be_rewritten(): void
    {
        $this->fund();
        $this->apply('cash_entry', ['direction' => 'credit', 'amount_minor' => 1000, 'treasury_account_id' => $this->treasury->id,
            'allocations' => [['account_code' => 'INVESTMENT_INCOME', 'amount_minor' => 1000]]], '2026-09-03');
        $distribution = $this->apply('distribution_declare', ['amount_minor' => 1000], '2026-09-04');
        $this->expectException(QueryException::class);
        DB::table('club_distributions')->where('id', $distribution['distribution_id'])->update(['record_date' => '2026-09-01']);
    }

    public function test_wrong_ownership_quantity_is_not_silently_ignored(): void
    {
        $this->fund();
        $this->expectException(InvalidArgumentException::class);
        $this->apply('redemption', ['member_user_id' => $this->owner->id, 'units_micro' => 1000,
            'capital_minor' => 1000, 'treasury_account_id' => $this->treasury->id], '2026-09-03');
    }
}
