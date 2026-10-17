<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialAspirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_can_create_private_planning_only_aspiration_and_choose_priority(): void
    {
        $user = $this->customer();
        Sanctum::actingAs($user);

        $first = $this->postJson('/api/financial-aspirations', [
            'title' => 'Prepare for school fees',
            'category' => 'education',
            'target_amount_minor' => 250000,
        ])->assertCreated()->assertJsonPath('data.financial_state', 'plan_only');
        $second = $this->postJson('/api/financial-aspirations', [
            'title' => 'Build an emergency fund',
            'category' => 'stability',
        ])->assertCreated();

        $firstRef = $first->json('data.reference');
        $secondRef = $second->json('data.reference');

        $this->patchJson('/api/financial-aspirations/'.$firstRef, ['is_priority' => true])
            ->assertOk()->assertJsonPath('data.is_priority', true);

        $this->patchJson('/api/financial-aspirations/'.$secondRef, ['is_priority' => true])
            ->assertOk()->assertJsonPath('data.is_priority', true);

        $this->getJson('/api/financial-aspirations')
            ->assertOk()
            ->assertJsonPath('data.financial_state', 'plan_only')
            ->assertJsonCount(2, 'data.aspirations')
            ->assertJsonPath('data.aspirations.0.reference', $secondRef)
            ->assertJsonPath('data.aspirations.1.is_priority', false);

        $this->patchJson('/api/financial-aspirations/'.$secondRef, ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('data.is_priority', false)
            ->assertJsonPath('data.status', 'paused');
    }

    public function test_aspirations_are_not_accessible_across_people(): void
    {
        $institution = $this->institution();
        $first = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'institution_id' => $institution->id]);
        $second = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'institution_id' => $institution->id]);

        Sanctum::actingAs($first);
        $created = $this->postJson('/api/financial-aspirations', ['title' => 'My own plan'])
            ->assertCreated();

        Sanctum::actingAs($second);
        $this->getJson('/api/financial-aspirations')->assertOk()->assertJsonCount(0, 'data.aspirations');
        $this->patchJson('/api/financial-aspirations/'.$created->json('data.reference'), ['title' => 'Intrusion'])
            ->assertNotFound();
    }

    public function test_an_aspiration_cannot_claim_a_verified_deposit_or_receive_unknown_fields(): void
    {
        Sanctum::actingAs($this->customer());
        $this->postJson('/api/financial-aspirations', [
            'title' => 'Future goal',
            'confirmed_balance_minor' => 500000,
            'is_priority' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_priority', false)
            ->assertJsonPath('data.financial_state', 'plan_only');

        $this->getJson('/api/financial-compass')->assertOk()
            ->assertJsonPath('data.position.available_money_minor', null)
            ->assertJsonPath('data.position.safe_to_spend_minor', null);
    }

    public function test_a_paused_goal_cannot_be_selected_as_priority(): void
    {
        Sanctum::actingAs($this->customer());
        $ref = $this->postJson('/api/financial-aspirations', ['title' => 'Education plan'])
            ->assertCreated()->json('data.reference');

        $this->patchJson('/api/financial-aspirations/'.$ref, ['status' => 'paused'])
            ->assertOk();
        $this->patchJson('/api/financial-aspirations/'.$ref, ['is_priority' => true])
            ->assertStatus(422);
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
            'name' => 'Aspirations test '.fake()->unique()->numerify('###'),
            'address' => 'Kampala',
            'phone' => fake()->unique()->numerify('25670#######'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }
}
