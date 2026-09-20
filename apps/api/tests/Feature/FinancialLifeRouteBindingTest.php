<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialLifeRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_manage_records_only_in_the_requested_space(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $space = $this->postJson('/api/financial-spaces', ['type' => 'savings_group', 'name' => 'First group'])
            ->assertCreated()->json('data.space.id');
        $other = $this->postJson('/api/financial-spaces', ['type' => 'savings_group', 'name' => 'Second group'])
            ->assertCreated()->json('data.space.id');
        $base = '/api/financial-spaces/'.$space;
        $this->postJson($base.'/assets', ['asset_type' => 'cash_box', 'name' => 'Group cash', 'value_minor' => 1000])->assertCreated();
        $obligation = $this->postJson($base.'/obligations', ['kind' => 'personal_debt', 'direction' => 'i_owe', 'amount_minor' => 600])
            ->assertCreated()->json('data.id');
        $this->getJson($base.'/assets')->assertOk()->assertJsonCount(1, 'data.assets');
        $this->getJson($base.'/obligations')->assertOk()->assertJsonCount(1, 'data.obligations');
        $this->getJson('/api/financial-spaces/'.$other.'/assets')->assertOk()->assertJsonCount(0, 'data.assets');
        $this->getJson('/api/financial-spaces/'.$other.'/obligations')->assertOk()->assertJsonCount(0, 'data.obligations');
        $this->postJson('/api/financial-spaces/'.$other.'/obligations/'.$obligation.'/settlements', ['amount_minor' => 200])->assertNotFound();
        $this->assertDatabaseHas('financial_obligations', ['id' => $obligation, 'outstanding_amount_minor' => 600]);
        $this->postJson($base.'/obligations/'.$obligation.'/settlements', ['amount_minor' => 200])
            ->assertOk()->assertJsonPath('data.outstanding_amount_minor', 400);
        $this->assertDatabaseHas('financial_obligations', ['id' => $obligation, 'financial_space_id' => $space, 'outstanding_amount_minor' => 400]);
        $this->getJson($base.'/financial-life')->assertOk()->assertJsonPath('data.net_worth_minor', 600);

        Sanctum::actingAs(User::factory()->create());
        foreach (['assets', 'obligations', 'financial-life'] as $endpoint) {
            $this->getJson($base.'/'.$endpoint)->assertForbidden();
        }
        $this->postJson($base.'/assets', ['asset_type' => 'cash_box', 'name' => 'Denied', 'value_minor' => 2000])->assertForbidden();
        $this->postJson($base.'/obligations', ['kind' => 'personal_debt', 'direction' => 'i_owe', 'amount_minor' => 600])->assertForbidden();
        $this->postJson($base.'/obligations/'.$obligation.'/settlements', ['amount_minor' => 400])->assertForbidden();
        $this->assertDatabaseCount('financial_assets', 1);
        $this->assertDatabaseCount('financial_obligations', 1);
        $this->assertDatabaseHas('financial_obligations', ['id' => $obligation, 'outstanding_amount_minor' => 400]);
    }

    public function test_unknown_spaces_return_not_found_before_membership_or_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $base = '/api/financial-spaces/999999';
        foreach (['assets', 'obligations', 'financial-life'] as $endpoint) {
            $this->getJson($base.'/'.$endpoint)->assertNotFound();
        }
        $this->postJson($base.'/assets', [])->assertNotFound();
        $this->postJson($base.'/obligations', [])->assertNotFound();
        $this->postJson($base.'/obligations/999999/settlements', [])->assertNotFound();
        $this->assertDatabaseCount('financial_assets', 0);
        $this->assertDatabaseCount('financial_obligations', 0);
    }
}
