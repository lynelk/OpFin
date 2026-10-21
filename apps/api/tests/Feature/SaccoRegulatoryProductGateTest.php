<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaccoRegulatoryProductGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sacco_product_requires_distinct_checker_and_reviewed_jurisdictional_evidence(): void
    {
        $owner = User::factory()->create();
        $treasurer = User::factory()->create();
        $member = User::factory()->create();
        $platform = User::factory()->create(['role' => 'platform_admin']);

        Sanctum::actingAs($owner);
        $spaceId = $this->postJson('/api/financial-spaces', [
            'type' => 'sacco', 'name' => 'Member Cooperative',
        ])->assertCreated()->json('data.space.id');
        foreach ([$treasurer->id => 'treasurer', $member->id => 'member'] as $userId => $role) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $spaceId,
                'user_id' => $userId,
                'role' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $base = '/api/financial-spaces/'.$spaceId.'/sacco';
        $product = $this->postJson($base.'/products', [
            'code' => 'SAVE-01',
            'name' => 'Members savings',
            'product_type' => 'savings',
            'withdrawable' => true,
        ])->assertCreated()->assertJsonPath('data.product.status', 'draft')
            ->json('data.product.id');
        $this->postJson($base.'/accounts', [
            'user_id' => $member->id,
            'product_id' => $product,
        ])->assertStatus(409);
        $this->postJson($base.'/products/'.$product.'/activate', [
            'attestation_id' => 999999,
        ])->assertForbidden();

        $attestation = $this->postJson($base.'/regulatory-attestations', [
            'issuing_authority' => 'Relevant jurisdictional authority',
            'licence_category' => 'Member cooperative operating permission',
            'evidence_reference' => 'verified-review-case-001',
            'permitted_product_types' => ['savings'],
            'valid_from' => '2026-01-01',
        ])->assertCreated()->assertJsonPath('data.provider_activation_enabled', false)
            ->json('data.attestation_id');
        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/products/'.$product.'/activate', [
            'attestation_id' => $attestation,
        ])->assertUnprocessable();

        Sanctum::actingAs($platform);
        $this->postJson('/api/admin/sacco/'.$spaceId.'/regulatory-attestations/'.$attestation.'/review', [
            'decision' => 'reviewed',
        ])->assertOk()->assertJsonPath('data.reviewed_document_only', true);

        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/products/'.$product.'/activate', [
            'attestation_id' => $attestation,
        ])->assertOk()->assertJsonPath('data.product.status', 'active')
            ->assertJsonPath('data.regulator_live_verification', false);
        $this->postJson($base.'/products/'.$product.'/activate', [
            'attestation_id' => $attestation,
        ])->assertStatus(409);
        $this->postJson($base.'/accounts', [
            'user_id' => $member->id,
            'product_id' => $product,
        ])->assertCreated();
    }
}
