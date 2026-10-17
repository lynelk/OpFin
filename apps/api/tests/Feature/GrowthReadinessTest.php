<?php

namespace Tests\Feature;

use App\Models\CapitalMandate;
use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_does_not_treat_configuration_as_financial_authorisation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $institution = Institution::create([
            'name' => 'Synthetic test institution', 'address' => 'Test road',
            'phone' => '0000000', 'email' => 'synthetic@example.test', 'country' => 'UG',
            'status' => 'Active', 'lender_relationship' => 'independent',
            'authority_basis' => 'licensed', 'authority_reference' => 'TEST-ONLY',
            'regulator_code' => 'TEST',
        ]);
        $product = LoanProduct::create([
            'name' => 'Synthetic stock finance', 'type' => 'Cash', 'status' => 'Active',
            'institution_id' => $institution->id, 'country' => 'UG', 'currency' => 'UGX',
            'product_category' => 'merchant_stock_finance',
        ]);
        LoanProductTerm::create([
            'loan_product_id' => $product->id, 'status' => 'Active',
            'duration' => 30, 'interest_type' => 'Flat', 'interest_cycle' => 'monthly',
            'repayment_frequency' => 'monthly', 'interest_rate' => 0,
        ]);
        CapitalMandate::create([
            'reference' => (string) Str::uuid(), 'owner_user_id' => $admin->id,
            'mandate_type' => 'portfolio', 'name' => 'Synthetic funding',
            'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now(),
            'committed_capital_minor' => 100000, 'deployed_capital_minor' => 25000,
            'reserved_capital_minor' => 15000,
        ]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/growth-readiness')->assertOk()
            ->assertJsonPath('data.products.0.configuration_status', 'configured')
            ->assertJsonPath('data.products.0.available_to_customer', null)
            ->assertJsonPath('data.capital_mandates.0.remaining_committed_capital_minor', 60000)
            ->assertJsonPath('data.capital_mandates.0.investor_participation_live', null)
            ->assertJsonPath('data.summary.configured_products', 1)
            ->assertJsonPath('data.statement_provider_readiness.mtn_momo_uganda.production_accepted', false);
    }

    public function test_customer_cannot_see_institutional_growth_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));
        $this->getJson('/api/admin/growth-readiness')->assertForbidden();
    }
}
