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
            ->assertJsonPath('data.capabilities.borrow.status', 'AVAILABLE')
            ->assertJsonPath('data.capabilities.ussd.status', 'AVAILABLE')
            ->assertJsonPath('data.capabilities.p2p_participatory_finance.status', 'DORMANT_READY')
            ->assertJsonPath('data.capabilities.community_growth_circles.public_name', 'Community Growth Circles')
            ->assertJsonPath('data.capabilities.workplace_support_finance.public_name', 'Workplace Support Finance')
            ->assertJsonPath('data.capabilities.member_growth_score.status', 'READY_FOR_INTERNAL_USE')
            ->assertJsonPath('data.capabilities.member_cooperative_core.status', 'DORMANT_READY')
            ->assertJsonPath('data.capabilities.linked_accounts.status', 'AVAILABLE')
            ->assertJsonPath('data.capabilities.partner_distribution.status', 'AVAILABLE')
            ->assertJsonPath('data.capabilities.payments.owner', 'opfin')
            ->assertJsonPath('data.capabilities.payments.primary_gateway', 'cpay_via_cito')
            ->assertJsonPath('data.capabilities.payments.status', 'AVAILABLE');
    }

    public function test_capabilities_with_external_dependencies_name_the_activation_gate(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/capabilities?country=UG')
            ->assertOk()
            ->assertJsonPath('data.capabilities.whatsapp.external_gate', 'meta_whatsapp_production_credentials')
            ->assertJsonPath('data.capabilities.investments.external_gate', 'licensed_investment_provider_custody_and_settlement')
            ->assertJsonPath('data.capabilities.p2p_participatory_finance.external_gate', 'community_growth_circle_governance_custody_regulatory_signoff')
            ->assertJsonPath('data.capabilities.sacco.public_name', 'Member Cooperative Core')
            ->assertJsonPath('data.capabilities.employer.public_name', 'Workplace Support Finance');
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
