<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalFinancialSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_space_listing_self_heals_one_canonical_personal_space_and_keeps_it_first(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/financial-spaces')
            ->assertOk()
            ->assertJsonCount(1, 'data.spaces')
            ->assertJsonPath('data.spaces.0.type', 'personal')
            ->assertJsonPath('data.spaces.0.name', 'My Money');

        $personalId = (int) $first->json('data.spaces.0.id');

        $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Future Builders Club',
            'country' => 'GH',
            'currency' => 'GHS',
        ])->assertCreated();

        $this->getJson('/api/financial-spaces')
            ->assertOk()
            ->assertJsonCount(2, 'data.spaces')
            ->assertJsonPath('data.spaces.0.id', $personalId)
            ->assertJsonPath('data.spaces.0.type', 'personal')
            ->assertJsonPath('data.spaces.1.type', 'investment_club');

        $this->getJson('/api/financial-spaces')->assertOk();

        $this->assertSame(
            1,
            DB::table('financial_space_memberships as memberships')
                ->join('financial_spaces as spaces', 'spaces.id', '=', 'memberships.financial_space_id')
                ->where('memberships.user_id', $user->id)
                ->where('memberships.status', 'active')
                ->whereNull('memberships.deleted_at')
                ->where('spaces.type', 'personal')
                ->where('spaces.status', 'active')
                ->whereNull('spaces.deleted_at')
                ->count()
        );
    }

    public function test_personal_wellbeing_records_are_bound_to_the_canonical_personal_space(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($user);

        $account = $this->postJson('/api/financial-accounts', [
            'display_name' => 'Main mobile money',
            'account_type' => 'mobile_money',
            'balance_minor' => 275000,
            'currency' => 'UGX',
        ])->assertCreated();

        $accountId = (int) $account->json('data.id');
        $spaceId = (int) DB::table('financial_spaces as spaces')
            ->join('financial_space_memberships as memberships', 'memberships.financial_space_id', '=', 'spaces.id')
            ->where('memberships.user_id', $user->id)
            ->where('spaces.type', 'personal')
            ->value('spaces.id');

        $this->assertGreaterThan(0, $spaceId);
        $this->assertDatabaseHas('financial_accounts', [
            'id' => $accountId,
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'balance_minor' => 275000,
        ]);

        $this->getJson('/api/financial-compass')
            ->assertOk()
            ->assertJsonPath('data.position.available_money_minor', 275000);
    }
}
