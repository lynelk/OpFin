<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpFinServiceRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_requires_authentication(): void
    {
        $this->getJson('/api/service-registry')->assertUnauthorized();
    }

    public function test_registry_exposes_all_people_modules_and_shared_tools_as_discovery_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $response = $this->getJson('/api/service-registry')->assertOk()
            ->assertJsonPath('data.discovery_only', true)
            ->assertJsonPath('data.activation_authoritative', false)
            ->assertJsonPath('data.registry.schema_version', 1)
            ->assertJsonPath('data.registry.services.people.owner', 'people')
            ->assertJsonCount(10, 'data.registry.services.people.modules');
        foreach (['budgets', 'asset_register', 'debt_management', 'accounting', 'approvals', 'documents', 'reporting'] as $tool) {
            $this->assertNotNull($response->json('data.registry.tools.'.$tool));
        }
        $this->assertContains('sacco', $response->json('data.registry.spaces'));
        $this->assertContains('investment_club', $response->json('data.registry.spaces'));
    }
}
