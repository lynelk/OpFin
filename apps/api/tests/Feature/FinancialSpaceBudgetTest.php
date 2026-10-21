<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpaceBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_endpoints_require_authentication(): void
    {
        $this->getJson('/api/financial-spaces/1/budgets')->assertUnauthorized();
        $this->postJson('/api/financial-spaces/1/budgets', [])->assertUnauthorized();
    }

    public function test_one_shared_budget_table_is_partitioned_by_financial_space(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $a = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'A',
        ])->assertCreated()->json('data.space.id');
        $b = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club', 'name' => 'B',
        ])->assertCreated()->json('data.space.id');

        $budget = $this->postJson('/api/financial-spaces/'.$a.'/budgets', [
            'category' => 'Meetings',
            'monthly_limit_minor' => 125000,
            'effective_from' => '2026-10-01',
            'alert_threshold_percent' => 85,
        ])->assertCreated()
            ->assertJsonPath('data.budget.financial_space_id', $a)
            ->assertJsonPath('data.budget.currency', 'UGX');

        $id = (int) $budget->json('data.budget.id');
        $this->getJson('/api/financial-spaces/'.$a.'/budgets?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.budgets.0.category', 'Meetings')
            ->assertJsonPath('data.actuals_status', 'statement_matched_only');
        $this->getJson('/api/financial-spaces/'.$b.'/budgets?month=2026-10')
            ->assertOk()->assertJsonCount(0, 'data.budgets');

        $this->patchJson('/api/financial-spaces/'.$b.'/budgets/'.$id, [
            'monthly_limit_minor' => 999999,
        ])->assertNotFound();
        $this->patchJson('/api/financial-spaces/'.$a.'/budgets/'.$id, [
            'monthly_limit_minor' => 85000,
        ])->assertOk()->assertJsonPath('data.budget.monthly_limit_minor', 85000);
        $this->assertDatabaseHas('financial_budgets', [
            'id' => $id, 'financial_space_id' => $a, 'monthly_limit_minor' => 85000,
        ]);
    }

    public function test_budget_alerts_count_only_statement_matched_and_explicitly_classified_debits(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $spaceId = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Community budget',
        ])->assertCreated()->json('data.space.id');
        $base = '/api/financial-spaces/'.$spaceId;
        $this->postJson($base.'/budgets', [
            'category' => 'Meetings', 'monthly_limit_minor' => 10000,
            'effective_from' => '2026-10-01', 'alert_threshold_percent' => 80,
        ])->assertCreated();

        $account = $this->postJson($base.'/treasury/accounts', [
            'account_name' => 'Group cashbox', 'account_type' => 'cash', 'currency' => 'UGX',
        ])->assertCreated();
        $accountId = $account->json('data.account.id');
        $this->assertNotNull($accountId);

        $records = [
            ['amount_minor' => 8500, 'category' => 'Meetings', 'matched' => true],
            ['amount_minor' => 2000, 'category' => 'Meetings', 'matched' => false],
            ['amount_minor' => 1100, 'category' => null, 'matched' => true],
        ];
        foreach ($records as $i => $record) {
            $response = $this->postJson($base.'/treasury/accounts/'.$accountId.'/transactions', [
                'direction' => 'debit',
                'amount_minor' => $record['amount_minor'],
                'currency' => 'UGX',
                'description' => 'Budget test transaction '.$i,
                'transaction_date' => '2026-10-12',
                'idempotency_key' => 'budget-actual-test-'.$i,
                'metadata' => $record['category'] === null ? [] : ['budget_category' => $record['category']],
            ])->assertCreated();

            if ($record['matched']) {
                $transactionId = $response->json('data.transaction.id');
                DB::table('financial_space_transactions')
                    ->where('id', $transactionId)
                    ->where('financial_space_id', $spaceId)
                    ->update(['reconciliation_status' => 'matched', 'reconciled_at' => now()]);
            }
        }

        $this->getJson($base.'/budgets?month=2026-10')
            ->assertOk()
            ->assertJsonPath('data.budgets.0.statement_matched_actual_minor', 8500)
            ->assertJsonPath('data.budgets.0.alert_threshold_reached', true)
            ->assertJsonPath('data.budgets.0.over_planned_limit', false)
            ->assertJsonPath('data.budgets.0.remaining_planned_minor', 1500)
            ->assertJsonPath('data.matched_debits_minor', 9600)
            ->assertJsonPath('data.unallocated_matched_debits_minor', 1100)
            ->assertJsonPath('data.matched_transaction_count', 2);
    }

    public function test_member_can_read_but_not_change_budget_or_financial_space_records(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Sanctum::actingAs($owner);
        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Savers',
        ])->assertCreated()->json('data.space.id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $space,
            'user_id' => $member->id,
            'status' => 'active',
            'role' => 'member',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $url = '/api/financial-spaces/'.$space.'/budgets';
        $this->getJson($url)->assertOk();
        $this->postJson($url, [
            'category' => 'Other', 'monthly_limit_minor' => 4000,
            'effective_from' => '2026-10-01',
        ])->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, [
            'category' => 'Other', 'monthly_limit_minor' => 4000,
            'effective_from' => '2026-10-01',
        ])->assertForbidden();
    }
}
