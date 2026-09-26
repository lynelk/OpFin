<?php

namespace Tests\Feature;

use App\Exceptions\EssentialsCustomerBusy;
use App\Models\EssentialsBiller;
use App\Models\User;
use App\Services\EssentialsOrchestrationService;
use App\Services\EssentialsPartnerScopeService;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class EssentialsAccountConflictResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_customer_returns_conflict_without_changing_existing_authorisation_errors(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $partner = User::factory()->create(['role' => 'partner_api']);
        $space = app(PersonalFinancialSpaceService::class)->ensure($customer);
        $partnerId = (int) DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => (string) Str::uuid(), 'created_by' => $partner->id,
            'partner_name' => 'Synthetic conflict test', 'partner_type' => 'platform', 'status' => 'active',
            'allowed_products' => '["essentials"]', 'commercial_terms' => '{}',
            'approved_by' => $partner->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $input = [
            'partner_account_id' => $partnerId,
            'biller_id' => EssentialsBiller::where('code', 'UEDCL')->firstOrFail()->id,
            'financial_space_id' => $space->id, 'account_reference' => 'SYNTHETIC-CONFLICT-METER',
            'source_platform' => 'synthetic-test', 'customer_consent_reference' => 'synthetic-consent',
        ];
        $scope = Mockery::mock(EssentialsPartnerScopeService::class);
        $scope->shouldReceive('resolve')->once()->andReturn($space->id);
        $this->app->instance(EssentialsPartnerScopeService::class, $scope);
        $service = Mockery::mock(EssentialsOrchestrationService::class);
        $service->shouldReceive('createAccount')->once()->andThrow(new EssentialsCustomerBusy('Another financial operation is in progress.'));
        $this->app->instance(EssentialsOrchestrationService::class, $service);
        Sanctum::actingAs($partner);
        $url = '/api/partner/essentials/customers/'.$customer->id.'/accounts';
        $this->postJson($url, $input)->assertConflict()->assertJsonPath('success', false);
        $this->assertDatabaseMissing('essentials_accounts', ['user_id' => $customer->id]);

        $customer->delete();
        $this->postJson($url, $input)->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['role' => 'partner_api']));
        $this->postJson($url, $input)->assertForbidden();
    }
}
