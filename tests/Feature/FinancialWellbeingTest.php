<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialWellbeingTest extends TestCase
{
    use RefreshDatabase;

    public function test_compass_refuses_to_invent_safe_to_spend_without_a_balance_source(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->getJson('/api/financial-compass')
            ->assertOk()
            ->assertJsonPath('data.position.available_money_minor', null)
            ->assertJsonPath('data.position.safe_to_spend_minor', null)
            ->assertJsonPath('data.next_best_action.code', 'record_balance');
    }

    public function test_budget_cash_flow_calendar_and_safe_to_spend_are_integrated(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson('/api/financial-accounts', [
            'display_name' => 'Primary mobile money',
            'account_type' => 'mobile_money',
            'balance_minor' => 500000,
            'currency' => 'UGX',
        ])->assertCreated()
            ->assertJsonPath('data.confidence', 'user_reported');

        $this->postJson('/api/budgets', [
            'category' => 'Food',
            'monthly_limit_minor' => 100000,
            'effective_from' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $entry = $this->postJson('/api/cash-flow/entries', [
            'direction' => 'expense',
            'amount_minor' => 25000,
            'description' => 'Supermarket groceries',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ])->assertCreated();
        $entry->assertJsonPath('data.category', 'Food');

        $this->postJson('/api/financial-calendar/events', [
            'title' => 'Electricity bill',
            'event_type' => 'bill',
            'direction' => 'expense',
            'amount_minor' => 120000,
            'scheduled_for' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'certainty' => 'scheduled',
            'category' => 'Utilities',
        ])->assertCreated();

        $this->getJson('/api/financial-compass')
            ->assertOk()
            ->assertJsonPath('data.position.available_money_minor', 500000)
            ->assertJsonPath('data.position.committed_money_minor', 120000)
            ->assertJsonPath('data.position.safe_to_spend_minor', 380000)
            ->assertJsonPath('data.cash_flow.expense_minor', 25000)
            ->assertJsonPath('data.budgets.0.category', 'Food')
            ->assertJsonPath('data.budgets.0.actual_minor', 25000)
            ->assertJsonPath('data.budgets.0.remaining_minor', 75000);
    }

    public function test_user_can_override_automatic_category_and_recurring_events_are_projected(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $entry = $this->postJson('/api/cash-flow/entries', [
            'direction' => 'expense',
            'amount_minor' => 15000,
            'description' => 'Restaurant lunch',
            'occurred_at' => now()->format('Y-m-d H:i:s'),
        ])->assertCreated();

        $entryId = (int) $entry->json('data.id');
        $this->patchJson("/api/cash-flow/entries/{$entryId}", [
            'category' => 'Family Support',
        ])->assertOk()
            ->assertJsonPath('data.category', 'Family Support')
            ->assertJsonPath('data.category_overridden', true);

        $this->postJson('/api/financial-calendar/events', [
            'title' => 'Monthly rent',
            'event_type' => 'bill',
            'direction' => 'expense',
            'amount_minor' => 300000,
            'scheduled_for' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'certainty' => 'scheduled',
            'recurrence' => 'monthly',
            'category' => 'Rent',
        ])->assertCreated();

        $response = $this->getJson('/api/financial-calendar?to='.now()->addDays(70)->toDateString())
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, count($response->json('data.events')));
        $response->assertJsonPath('data.certainty_legend.predicted', 'Model-derived forecast; not guaranteed cash.');
    }

    public function test_financial_data_is_isolated_by_authenticated_user(): void
    {
        $institution = $this->institution();
        $first = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
        ]);
        $second = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
        ]);

        Sanctum::actingAs($first);
        $this->postJson('/api/financial-accounts', [
            'display_name' => 'Private balance',
            'account_type' => 'cash',
            'balance_minor' => 777000,
        ])->assertCreated();

        Sanctum::actingAs($second);
        $this->getJson('/api/financial-accounts')
            ->assertOk()
            ->assertJsonCount(0, 'data.accounts');
        $this->getJson('/api/financial-compass')
            ->assertOk()
            ->assertJsonPath('data.position.available_money_minor', null);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $this->institution()->id,
        ]);
    }

    private function institution(): Institution
    {
        return Institution::create([
            'name' => 'Financial Wellbeing Institution '.fake()->unique()->numerify('###'),
            'address' => 'Kampala',
            'phone' => fake()->unique()->numerify('25670#######'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }
}
