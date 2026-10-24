<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebOnboardingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_registration_fails_closed_when_approved_legal_versions_are_missing(): void
    {
        Config::set('services.opfin.legal_terms_version', null);
        Config::set('services.opfin.legal_privacy_version', null);

        $this->postJson('/api/register', [
            'first_name' => 'Test', 'last_name' => 'Customer',
            'phone' => '256700123456', 'verification_token' => str_repeat('a', 64),
            'pin' => '826419', 'pin_confirmation' => '826419',
            'terms_accepted' => true, 'acquisition_channel' => 'web',
        ])->assertStatus(503);
        $this->assertDatabaseMissing('users', ['phone' => '256700123456']);
    }

    public function test_web_registration_rejects_outdated_legal_version(): void
    {
        Config::set('services.opfin.legal_terms_version', 'legal-approved-v2');
        Config::set('services.opfin.legal_privacy_version', 'privacy-approved-v3');

        $this->postJson('/api/register', [
            'first_name' => 'Test', 'last_name' => 'Customer',
            'phone' => '256700123457', 'verification_token' => str_repeat('b', 64),
            'pin' => '826419', 'pin_confirmation' => '826419',
            'terms_accepted' => true, 'acquisition_channel' => 'web',
            'terms_policy_version' => 'legal-approved-v1',
            'privacy_policy_version' => 'privacy-approved-v3',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('users', ['phone' => '256700123457']);
    }

    public function test_organisation_onboarding_can_be_saved_resumed_and_read_only_by_members(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $stranger = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);
        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'Consent Test Limited',
        ])->assertCreated();
        $spaceId = $space->json('data.space.id');
        $this->putJson("/api/financial-spaces/{$spaceId}/organisation-onboarding", [
            'stage' => 'kyb', 'requirements' => ['registration_number' => 'TEST-1'],
            'submit' => false,
        ])->assertOk();
        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")
            ->assertOk()->assertJsonPath('data.onboarding.stage', 'kyb')
            ->assertJsonPath('data.onboarding.status', 'draft');
        Sanctum::actingAs($stranger);
        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")->assertForbidden();
        $this->putJson("/api/financial-spaces/{$spaceId}/organisation-onboarding", [
            'stage' => 'certification', 'submit' => true,
        ])->assertForbidden();
    }

    public function test_institutional_evidence_is_not_visible_to_ordinary_members_or_other_spaces(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $member = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $outsider = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Sanctum::actingAs($owner);
        $spaceId = $this->postJson('/api/financial-spaces', [
            'type' => 'sacco', 'name' => 'Test Community Society',
        ])->assertCreated()->json('data.space.id');
        $this->putJson("/api/financial-spaces/{$spaceId}/organisation-onboarding", [
            'stage' => 'kyb',
            'requirements' => ['registration_number' => 'PRIVATE-REG-001'],
            'evidence' => ['internal_note' => 'Institution due diligence only'],
        ])->assertOk()->assertHeader('Cache-Control', 'private, no-store');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")
            ->assertOk()->assertJsonPath('data.onboarding.stage', 'kyb')
            ->assertSee('PRIVATE-REG-001');

        Sanctum::actingAs($member);
        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertJsonPath('data.onboarding', null)
            ->assertDontSee('PRIVATE-REG-001')
            ->assertDontSee('Institution due diligence only');
        $this->putJson("/api/financial-spaces/{$spaceId}/organisation-onboarding", [
            'stage' => 'certification', 'submit' => true,
        ])->assertForbidden();

        Sanctum::actingAs($outsider);
        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")->assertForbidden();

        DB::table('financial_space_memberships')
            ->where('financial_space_id', $spaceId)
            ->where('user_id', $member->id)
            ->update(['status' => 'suspended']);
        Sanctum::actingAs($member);
        $this->getJson("/api/financial-spaces/{$spaceId}/workspace")->assertForbidden();
    }

    public function test_partial_draft_updates_preserve_original_evidence_and_submitted_state_is_locked(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($owner);
        $spaceId = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_fund', 'name' => 'Test Fund Limited',
        ])->assertCreated()->json('data.space.id');
        $endpoint = "/api/financial-spaces/{$spaceId}/organisation-onboarding";

        $this->putJson($endpoint, [
            'stage' => 'kyb',
            'requirements' => ['registered_name' => 'Test Fund Limited'],
            'evidence' => ['source_reference' => 'document-on-file'],
        ])->assertOk()->assertJsonPath('data.status', 'draft');

        $original = DB::table('organisation_onboarding_cases')
            ->where('financial_space_id', $spaceId)->first();

        $this->putJson($endpoint, ['stage' => 'products'])
            ->assertOk()->assertJsonPath('data.status', 'draft');

        $saved = DB::table('organisation_onboarding_cases')
            ->where('financial_space_id', $spaceId)->first();
        $this->assertSame($original->id, $saved->id);
        $this->assertSame($original->created_at, $saved->created_at);
        $this->assertSame($original->requirements, $saved->requirements);
        $this->assertSame($original->evidence, $saved->evidence);

        $this->putJson($endpoint, ['stage' => 'profile', 'submit' => true])->assertStatus(422);
        $this->putJson($endpoint, ['stage' => 'certification', 'submit' => true])
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        $submitted = DB::table('organisation_onboarding_cases')
            ->where('financial_space_id', $spaceId)->first();
        $this->assertNotNull($submitted->submitted_at);
        $this->assertNull($submitted->verified_at);

        $this->putJson($endpoint, [
            'stage' => 'kyb', 'submit' => false,
            'evidence' => ['source_reference' => 'altered-after-submit'],
        ])->assertStatus(409);
        $this->putJson($endpoint, [
            'stage' => 'certification', 'submit' => true,
        ])->assertStatus(409);

        $after = DB::table('organisation_onboarding_cases')
            ->where('financial_space_id', $spaceId)->first();
        $this->assertSame($submitted->id, $after->id);
        $this->assertSame($submitted->status, $after->status);
        $this->assertSame($submitted->submitted_at, $after->submitted_at);
        $this->assertSame($submitted->evidence, $after->evidence);
    }

}
