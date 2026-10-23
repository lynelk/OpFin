<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAwareWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/financial-spaces')->assertUnauthorized();
    }

    public function test_savings_groups_keep_stable_space_type_and_display_new_name(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Neighbourhood Savers',
        ])->assertCreated()->json('data.space.id');

        $spaces = $this->getJson('/api/financial-spaces')->assertOk();
        $group = collect($spaces->json('data.spaces'))->firstWhere('id', $id);
        $this->assertSame('savings_group', $group['type']);
        $this->assertSame('Savings Club / VSLA', $group['workspace']['label']);
        $this->assertTrue($group['workspace']['can_edit_finances']);
        $this->assertTrue($group['workspace']['can_manage_members']);
    }

    public function test_ordinary_group_members_can_view_but_cannot_change_shared_assets_or_debts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Open Savings',
        ])->assertCreated()->json('data.space.id');
        $base = '/api/financial-spaces/'.$id;
        $this->postJson($base.'/assets', [
            'asset_type' => 'cash_box', 'name' => 'Cash box', 'value_minor' => 1000,
        ])->assertCreated();
        $debtId = $this->postJson($base.'/obligations', [
            'kind' => 'loan', 'direction' => 'i_owe', 'amount_minor' => 600,
        ])->assertCreated()->json('data.id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $id, 'user_id' => $member->id,
            'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $listed = $this->getJson('/api/financial-spaces')->assertOk();
        $group = collect($listed->json('data.spaces'))->firstWhere('id', $id);
        $this->assertFalse($group['workspace']['can_edit_finances']);
        $this->assertFalse($group['workspace']['can_manage_members']);
        $this->getJson($base.'/financial-life')->assertOk();
        $this->getJson($base.'/assets')->assertOk();
        $this->getJson($base.'/obligations')->assertOk();
        $this->postJson($base.'/assets', [
            'asset_type' => 'cash_box', 'name' => 'Unauthorised', 'value_minor' => 2000,
        ])->assertForbidden();
        $this->postJson($base.'/obligations', [
            'kind' => 'loan', 'direction' => 'i_owe', 'amount_minor' => 2000,
        ])->assertForbidden();
        $this->postJson($base.'/obligations/'.$debtId.'/settlements', [
            'amount_minor' => 100,
        ])->assertForbidden();
        $this->assertDatabaseHas('financial_obligations', [
            'id' => $debtId, 'outstanding_amount_minor' => 600,
        ]);
    }

    public function test_inactive_spaces_and_suspended_memberships_are_not_switchable(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $inactiveId = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Closed firm',
        ])->assertCreated()->json('data.space.id');
        DB::table('financial_spaces')->where('id', $inactiveId)->update(['status' => 'inactive']);
        $suspendedId = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club', 'name' => 'Paused club',
        ])->assertCreated()->json('data.space.id');
        DB::table('financial_space_memberships')->where('financial_space_id', $suspendedId)
            ->where('user_id', $owner->id)->update(['status' => 'suspended']);

        $listed = $this->getJson('/api/financial-spaces')->assertOk();
        $ids = collect($listed->json('data.spaces'))->pluck('id');
        $this->assertNotContains($inactiveId, $ids);
        $this->assertNotContains($suspendedId, $ids);
    }

    public function test_people_management_capability_is_not_assumed_from_business_membership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Employer',
        ])->assertCreated()->json('data.space.id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $id, 'user_id' => $member->id,
            'role' => 'member', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Sanctum::actingAs($member);
        $spaces = $this->getJson('/api/financial-spaces')->assertOk();
        $business = collect($spaces->json('data.spaces'))->firstWhere('id', $id);
        $this->assertTrue($business['workspace']['can_open_people_connections']);
        $this->assertFalse($business['workspace']['can_manage_people']);
        $this->assertFalse($business['workspace']['can_edit_finances']);
        $this->assertFalse($business['workspace']['can_view_finances']);
        $this->getJson('/api/financial-spaces/'.$id.'/financial-life')->assertForbidden();
        $this->getJson('/api/financial-spaces/'.$id.'/budgets')->assertForbidden();
    }
}
