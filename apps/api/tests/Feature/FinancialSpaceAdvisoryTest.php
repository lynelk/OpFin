<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpaceAdvisoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_and_business_people_only_users_cannot_read_financial_alerts(): void
    {
        $this->getJson('/api/financial-spaces/1/advisories')->assertUnauthorized();

        $owner = User::factory()->create();
        $employee = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Employer with private finances',
        ])->assertCreated()->json('data.space.id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $id,
            'user_id' => $employee->id,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/financial-spaces/'.$id.'/advisories')->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson('/api/financial-spaces/'.$id.'/advisories')
            ->assertOk()
            ->assertJsonPath('data.credit_decision_eligible', false)
            ->assertJsonPath('data.automatic_payment_execution', false);
    }

    public function test_own_group_alerts_are_factual_and_do_not_mislabel_provider_state(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Savers',
        ])->assertCreated()->json('data.space.id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->postJson('/api/financial-spaces/'.$id.'/obligations', [
            'kind' => 'loan',
            'direction' => 'i_owe',
            'amount_minor' => 30000,
            'currency' => 'UGX',
            'due_date' => today()->subDay()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($member);
        $result = $this->getJson('/api/financial-spaces/'.$id.'/advisories')
            ->assertOk()
            ->assertJsonPath('data.debt_tracker.overdue_minor', 30000)
            ->assertJsonPath('data.recommendation_basis', 'reported_positions_only')
            ->assertJsonPath('data.observed_cashbook.not_bank_balance', true);
        $codes = collect($result->json('data.advisories'))->pluck('code');
        $this->assertContains('debt_overdue', $codes);
        $this->assertNotContains('offer_more_credit', $codes);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/financial-spaces/'.$id.'/advisories')->assertForbidden();
    }
}
