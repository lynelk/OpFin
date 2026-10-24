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
        $this->assertSame(
            ['b2c', 'b2b', 'b2b2c', 'c2b', 'c2b2b'],
            array_keys($response->json('data.registry.relationship_models'))
        );
        $this->assertSame(
            'personal_financial_life',
            $response->json('data.registry.relationship_models.b2c.source_foundation')
        );
        $this->assertSame(
            'independent_institution_management',
            $response->json('data.registry.relationship_models.b2b.scope')
        );
        $this->assertSame(
            'customer_initiated_financial_intent',
            $response->json('data.registry.relationship_models.c2b.scope')
        );
        $this->assertSame(
            'partner_financial_intent_requests',
            $response->json('data.registry.relationship_models.c2b2b.source_foundation')
        );
        $this->assertFalse($response->json('data.registry.relationship_models.c2b2b.multi_hop_generalised'));
        foreach (array_keys($response->json('data.registry.relationship_models')) as $mode) {
            $this->assertFalse($response->json('data.registry.relationship_models.'.$mode.'.provider_activated'));
        }
    }
}
