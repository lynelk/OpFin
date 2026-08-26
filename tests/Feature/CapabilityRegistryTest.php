<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapabilityRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_load_country_scoped_capabilities(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/capabilities?country=UG')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.country', 'UG')
            ->assertJsonPath('data.country_policy.currency', 'UGX')
            ->assertJsonPath('data.capabilities.home.status', 'AVAILABLE')
            ->assertJsonPath('data.capabilities.borrow.status', 'PILOT')
            ->assertJsonPath('data.capabilities.payments.owner', 'cpay')
            ->assertJsonPath('data.capabilities.payments.status', 'AVAILABLE');
    }

    public function test_capabilities_require_authentication(): void
    {
        $this->getJson('/api/capabilities')->assertUnauthorized();
    }

    public function test_unknown_country_policy_is_not_silently_accepted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/capabilities?country=ZZ')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
