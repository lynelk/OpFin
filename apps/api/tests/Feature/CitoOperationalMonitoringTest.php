<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitoOperationalMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cannot_read_provider_operations_or_costs(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));
        $this->getJson('/api/admin/cito-operations/snapshot')->assertForbidden();
    }

    public function test_authorised_operators_receive_local_evidence_without_false_certification(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_OPERATIONS]));
        $this->getJson('/api/admin/cito-operations/snapshot')
            ->assertOk()
            ->assertJsonPath('data.financial_finality_certified', false)
            ->assertJsonPath('data.recorded_provider_costs.alert', 'UNCONFIGURED')
            ->assertJsonPath('data.release_controls.ussd_external_cito_contract_verified', false);
    }
}
