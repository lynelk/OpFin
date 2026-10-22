<?php

namespace Tests\Feature;

use App\Models\FinancialSpace;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\FinancialSpaceActionSettlementService;
use App\Services\MemberSettlementJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberSettlementJournalTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $type = 'savings_group'): array
    {
        $owner = User::factory()->create();
        $checker = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => $type,
            'name' => 'Reconciliation test Space',
        ])->assertCreated()->json('data.space.id');
        foreach ([[$checker, 'treasurer'], [$member, 'member']] as [$user, $role]) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $treasury = DB::table('financial_space_treasury_accounts')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'financial_space_id' => $id,
            'account_name' => 'Reconciled bank',
            'account_type' => 'bank',
            'currency' => 'UGX',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $checker, $member, FinancialSpace::findOrFail($id), $treasury];
    }

    private function moneyAndCash(
        FinancialSpace $space,
        int $treasury,
        User $initiator,
        string $direction,
        int $amount,
        string $sourceType,
        string $sourceReference,
        bool $statementMatched = true,
        bool $providerMatched = true
    ): array {
        $public = (string) Str::uuid();
        $actionId = DB::table('financial_space_action_intents')->insertGetId([
            'public_id' => $public,
            'financial_space_id' => $space->id,
            'initiated_by_user_id' => $initiator->id,
            'action_type' => $direction === 'collection' ? 'contribution' : 'disbursement',
            'direction' => $direction,
            'principal_amount_minor' => $amount,
            'platform_fee_minor' => 0,
            'total_amount_minor' => $amount,
            'currency' => 'UGX',
            'counterparty_phone' => '256700000001',
            'status' => 'submitted',
            'idempotency_key' => (string) Str::uuid(),
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tx = MobileMoneyTransaction::create([
            'provider' => 'cito',
            'direction' => $direction,
            'amount_minor' => $amount,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'idempotency_key' => 'space-action:'.$public,
            'internal_reference' => 'OPF-SPACE-'.$public,
            'provider_reference' => 'provider-'.$public,
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
            'statement_reconciliation_status' => $providerMatched
                ? MobileMoneyTransaction::STATEMENT_MATCHED
                : MobileMoneyTransaction::STATEMENT_UNRECONCILED,
            'metadata' => [
                'purpose' => 'financial_space_action',
                'source_type' => 'financial_space_action',
                'source_id' => $actionId,
            ],
        ]);
        app(FinancialSpaceActionSettlementService::class)->recordProviderStatementMatch($tx);
        $cashId = DB::table('financial_space_transactions')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'financial_space_id' => $space->id,
            'treasury_account_id' => $treasury,
            'direction' => $direction === 'collection' ? 'credit' : 'debit',
            'amount_minor' => $amount,
            'currency' => 'UGX',
            'description' => 'Matched real-world settlement evidence fixture',
            'transaction_date' => today()->toDateString(),
            'source_type' => 'statement_import',
            'source_reference' => 'test:'.Str::uuid(),
            'reconciliation_status' => $statementMatched ? 'matched' : 'unreconciled',
            'reconciled_at' => $statementMatched ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$actionId, $cashId, $tx];
    }

    public function test_no_provider_match_or_treasury_match_can_post_member_funds(): void
    {
        [$owner, $checker, $member, $space, $treasury] = $this->context();
        $ledger = app(MemberSettlementJournalService::class);
        [$action, $cash] = $this->moneyAndCash(
            $space, $treasury, $member, 'collection', 40000,
            'sacco_member_cash', 'account:1:deposit', false, true
        );
        try {
            $ledger->post(
                $space, $action, $cash, 'sacco_member_cash', 'account:1:deposit',
                'sacco_member_deposit',
                ['code' => 'sacco_treasury_cash', 'member' => null],
                ['code' => 'sacco_member_deposits_liability', 'member' => $member->id],
                $checker->id
            );
            $this->fail('Unreconciled cashbook amount must not post.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('financial_space_member_journals', 0);
        [$action2, $cash2] = $this->moneyAndCash(
            $space, $treasury, $member, 'collection', 30000,
            'sacco_member_cash', 'account:1:deposit', true, false
        );
        try {
            $ledger->post(
                $space, $action2, $cash2, 'sacco_member_cash', 'account:1:deposit',
                'sacco_member_deposit',
                ['code' => 'sacco_treasury_cash', 'member' => null],
                ['code' => 'sacco_member_deposits_liability', 'member' => $member->id],
                $checker->id
            );
            $this->fail('Pending provider proof must not post.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('financial_space_member_journals', 0);
    }

    public function test_provider_matched_cashbook_event_posts_exactly_one_balanced_journal(): void
    {
        [$owner, $checker, $member, $space, $treasury] = $this->context();
        [$action, $cash, $provider] = $this->moneyAndCash(
            $space, $treasury, $member, 'collection', 50000,
            'sacco_member_cash', 'account:1:deposit'
        );
        $service = app(MemberSettlementJournalService::class);
        $args = [
            $space, $action, $cash, 'sacco_member_cash',
            'account:1:deposit', 'sacco_member_deposit',
            ['code' => 'sacco_treasury_cash', 'member' => null],
            ['code' => 'sacco_member_deposits_liability', 'member' => $member->id],
            $checker->id,
        ];
        $posted = $service->post(...$args);
        $this->assertSame(50000, (int) $posted->amount_minor);
        $this->assertSame('provider_success_and_statement_matched', $posted->evidence_level);
        $this->assertDatabaseCount('financial_space_member_journals', 1);
        $this->assertDatabaseCount('financial_space_member_journal_entries', 2);
        $this->assertSame(50000, (int) DB::table('financial_space_member_journal_entries')
            ->where('journal_id', $posted->id)->where('side', 'debit')->sum('amount_minor'));
        $this->assertSame(50000, (int) DB::table('financial_space_member_journal_entries')
            ->where('journal_id', $posted->id)->where('side', 'credit')->sum('amount_minor'));
        $again = $service->post(...$args);
        $this->assertSame((int) $posted->id, (int) $again->id);
        $this->assertDatabaseCount('financial_space_member_journals', 1);
        $this->assertDatabaseHas('financial_space_action_intents', [
            'id' => $action, 'status' => 'book_allocated',
        ]);

        $replayed = app(FinancialSpaceActionSettlementService::class)
            ->recordProviderStatementMatch($provider->fresh());
        $this->assertSame('book_allocated', $replayed->status);
    }

    public function test_wrong_source_is_rejected_before_recording_financial_entry(): void
    {
        [$owner, $checker, $member, $space, $treasury] = $this->context();
        [$action, $cash] = $this->moneyAndCash(
            $space, $treasury, $member, 'collection', 2000,
            'sacco_member_cash', 'account:2:deposit'
        );
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            app(MemberSettlementJournalService::class)->post(
                $space, $action, $cash, 'sacco_member_cash', 'account:1:deposit',
                'sacco_member_deposit',
                ['code' => 'sacco_treasury_cash', 'member' => null],
                ['code' => 'sacco_member_deposits_liability', 'member' => $member->id],
                $checker->id
            );
        } finally {
            $this->assertDatabaseCount('financial_space_member_journals', 0);
        }
    }
}
