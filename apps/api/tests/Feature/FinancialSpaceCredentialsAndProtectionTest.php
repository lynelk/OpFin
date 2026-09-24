<?php

namespace Tests\Feature;

use App\Models\ProtectionProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialSpaceCredentialsAndProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_investment_club_supports_external_registration_credentials_without_changing_its_internal_identity(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club',
            'name' => 'Future Builders Club',
            'country' => 'GH',
            'currency' => 'GHS',
        ])->assertCreated()
            ->assertJsonPath('data.space.type', 'investment_club');

        $spaceId = (int) $space->json('data.space.id');
        $publicId = (string) $space->json('data.space.public_id');

        $credential = $this->postJson("/api/financial-spaces/{$spaceId}/credentials", [
            'credential_type' => 'government_group_code',
            'issuer_code' => 'GOV-GH',
            'issuer_name' => 'Government group registry',
            'credential_value' => 'GGC-001234',
            'jurisdiction_country' => 'GH',
        ])->assertCreated()
            ->assertJsonPath('data.credential.verification_status', 'declared');

        $credentialId = (int) $credential->json('data.credential.id');

        $this->getJson("/api/financial-spaces/{$spaceId}/credentials")
            ->assertOk()
            ->assertJsonCount(1, 'data.credentials')
            ->assertJsonPath('data.credentials.0.credential_value', 'GGC-001234');

        $operator = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operator);

        $this->patchJson("/api/admin/financial-space-credentials/{$credentialId}/verification", [
            'verification_status' => 'verified',
            'verification_reference' => 'registry-check-001',
            'verification_evidence_hash' => hash('sha256', 'registry response'),
        ])->assertOk()
            ->assertJsonPath('data.credential.verification_status', 'verified');

        $this->assertDatabaseHas('financial_spaces', [
            'id' => $spaceId,
            'public_id' => $publicId,
            'type' => 'investment_club',
        ]);
        $this->assertDatabaseHas('financial_space_credentials', [
            'id' => $credentialId,
            'financial_space_id' => $spaceId,
            'verification_status' => 'verified',
            'verified_by_user_id' => $operator->id,
        ]);
    }

    public function test_group_protection_catalogue_only_returns_group_capable_products_to_members(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $spaceId = (int) $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group',
            'name' => 'Community Savers',
        ])->assertCreated()->json('data.space.id');

        $approver = User::factory()->create();

        ProtectionProduct::query()->create([
            'code' => 'GROUP-COVER-001',
            'name' => 'Group Personal Accident',
            'insurer_name' => 'Example Regulated Insurer',
            'partner_product_reference' => 'GROUP-001',
            'country_code' => 'UG',
            'currency' => 'UGX',
            'product_type' => 'personal_accident',
            'audience_scope' => 'group',
            'status' => ProtectionProduct::STATUS_ACTIVE,
            'premium_amount_minor' => 1000,
            'premium_frequency' => 'monthly',
            'coverage_limit_minor' => 100000,
            'disclosure_version' => 'v1',
            'benefits' => ['Accident benefit subject to issued terms'],
            'exclusions' => [],
            'disclosure_payload' => ['decision_authority' => 'insurer_or_underwriter'],
            'terms_url' => 'https://example.test/group-cover',
            'created_by' => $approver->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        ProtectionProduct::query()->create([
            'code' => 'PERSONAL-COVER-001',
            'name' => 'Personal Health Cover',
            'insurer_name' => 'Example Regulated Insurer',
            'partner_product_reference' => 'PERSONAL-001',
            'country_code' => 'UG',
            'currency' => 'UGX',
            'product_type' => 'health',
            'audience_scope' => 'personal',
            'status' => ProtectionProduct::STATUS_ACTIVE,
            'premium_amount_minor' => 1000,
            'premium_frequency' => 'monthly',
            'coverage_limit_minor' => 100000,
            'disclosure_version' => 'v1',
            'benefits' => ['Health benefit subject to issued terms'],
            'exclusions' => [],
            'disclosure_payload' => ['decision_authority' => 'insurer_or_underwriter'],
            'terms_url' => 'https://example.test/personal-cover',
            'created_by' => $approver->id,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->getJson("/api/financial-spaces/{$spaceId}/protection/products")
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.code', 'GROUP-COVER-001')
            ->assertJsonPath('data.products.0.audience_scope', 'group');

        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/financial-spaces/{$spaceId}/protection/products")
            ->assertForbidden();
    }
}
