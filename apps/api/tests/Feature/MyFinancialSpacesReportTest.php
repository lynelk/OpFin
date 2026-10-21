<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyFinancialSpacesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_view_requires_authentication(): void
    {
        $this->getJson('/api/my-spaces/report')->assertUnauthorized();
    }

    public function test_report_preserves_ownership_does_not_expose_employer_finances_or_convert_currencies(): void
    {
        $owner = User::factory()->create();
        $worker = User::factory()->create();
        Sanctum::actingAs($owner);
        $employerId = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Company Books',
        ])->assertCreated()->json('data.space.id');
        $saversId = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Community Savings',
        ])->assertCreated()->json('data.space.id');
        $this->postJson('/api/financial-spaces/'.$employerId.'/assets', [
            'asset_type' => 'equipment', 'name' => 'Company laptop', 'value_minor' => 900000,
        ])->assertCreated();
        $this->postJson('/api/financial-spaces/'.$saversId.'/assets', [
            'asset_type' => 'equipment', 'name' => 'Group safe', 'value_minor' => 300000,
        ])->assertCreated();

        foreach ([$employerId, $saversId] as $id) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $id,
                'user_id' => $worker->id,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($worker);
        $response = $this->getJson('/api/my-spaces/report')->assertOk()
            ->assertJsonPath('data.personal_aggregate_minor', null);
        $spaces = collect($response->json('data.spaces'));
        $this->assertFalse($spaces->contains('space_name', 'Company Books'));
        $group = $spaces->firstWhere('space_name', 'Community Savings');
        $this->assertNotNull($group);
        $this->assertSame('separate_shared_or_organisation_space', $group['ownership_scope']);
        $this->assertSame(300000, $group['recorded_assets_minor']);
        $this->assertSame('only_UGX', $group['currency_scope']);
        $this->assertSame('recorded_not_independently_settled', $group['evidence_status']);
    }
}
