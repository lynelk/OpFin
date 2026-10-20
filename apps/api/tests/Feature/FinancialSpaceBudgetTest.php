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
            ->assertJsonPath('data.actuals_status', 'not_included');
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
