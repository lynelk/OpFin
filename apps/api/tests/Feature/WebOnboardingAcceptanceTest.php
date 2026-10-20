<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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
}
