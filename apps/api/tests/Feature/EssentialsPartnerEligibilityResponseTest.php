<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EssentialsOrchestrationService;
use App\Services\EssentialsPartnerScopeService;
use App\Services\PersonalFinancialSpaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class EssentialsPartnerEligibilityResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_endpoint_never_returns_unscoped_eligibility_collections(): void
    {
        Http::preventStrayRequests();
        $customer = User::factory()->create(['role' => 'customer']);
        $partner = User::factory()->create(['role' => 'partner_api']);
        $space = app(PersonalFinancialSpaceService::class)->ensure($customer);
        $partnerId = $this->partnerAccount($partner);
        $scopes = Mockery::mock(EssentialsPartnerScopeService::class);
        $scopes->shouldReceive('resolve')->once()
            ->withArgs(fn ($subject, $account, $scope, $target): bool => $subject->id === $customer->id
                && $account === $partnerId && $scope === 'eligibility' && $target === $space->id)
            ->andReturn($space->id);
        $this->app->instance(EssentialsPartnerScopeService::class, $scopes);
        $service = Mockery::mock(EssentialsOrchestrationService::class);
        $service->shouldReceive('refreshEligibility')->once()
            ->withArgs(fn ($subject, $target, $channel): bool => $subject->id === $customer->id
                && $target === $space->id && $channel === 'partner')
            ->andReturn([
                'lines' => collect([
                    ['id' => 1, 'financial_space_id' => $space->id, 'currency' => 'UGX', 'available_limit_minor' => 90000,
                        'decision_snapshot' => ['source' => 'SYNTHETIC-PRIVATE-CREDIT-EVIDENCE']],
                    ['id' => 2, 'financial_space_id' => $space->id + 1000, 'currency' => 'UGX', 'available_limit_minor' => 200000],
                ]),
                'overall' => ['overall_available_limit_minor' => 60000,
                    'accounts' => [['private' => 'SYNTHETIC-PRIVATE-ACCOUNT']],
                    'advances' => [['private' => 'SYNTHETIC-PRIVATE-DEBT']]],
            ]);
        $this->app->instance(EssentialsOrchestrationService::class, $service);
        Sanctum::actingAs($partner);
        $response = $this->postJson('/api/partner/essentials/customers/'.$customer->id.'/eligibility', [
            'partner_account_id' => $partnerId, 'financial_space_id' => $space->id, 'channel' => 'partner',
        ])->assertOk()->assertJsonPath('data.financial_space_id', $space->id)
            ->assertJsonCount(1, 'data.lines')->assertJsonPath('data.lines.0.id', 1)
            ->assertJsonPath('data.overall.overall_available_limit_minor', 60000)
            ->assertJsonMissingPath('data.overall.accounts')->assertJsonMissingPath('data.overall.advances')
            ->assertJsonMissingPath('data.lines.0.decision_snapshot');
        $this->assertStringNotContainsString('SYNTHETIC-PRIVATE', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_a_denied_scope_never_runs_eligibility_or_projects_customer_data(): void
    {
        Http::preventStrayRequests();
        $customer = User::factory()->create(['role' => 'customer']);
        $partner = User::factory()->create(['role' => 'partner_api']);
        $space = app(PersonalFinancialSpaceService::class)->ensure($customer);
        $partnerId = $this->partnerAccount($partner);
        $scopes = Mockery::mock(EssentialsPartnerScopeService::class);
        $scopes->shouldReceive('resolve')->once()->andThrow(new InvalidArgumentException(
            'The customer has not authorised this operation in an active Financial Space.'
        ));
        $this->app->instance(EssentialsPartnerScopeService::class, $scopes);
        $service = Mockery::mock(EssentialsOrchestrationService::class);
        $service->shouldNotReceive('refreshEligibility');
        $this->app->instance(EssentialsOrchestrationService::class, $service);
        Sanctum::actingAs($partner);
        $this->postJson('/api/partner/essentials/customers/'.$customer->id.'/eligibility', [
            'partner_account_id' => $partnerId, 'financial_space_id' => $space->id,
        ])->assertUnprocessable()->assertJsonMissingPath('data.lines');
        Http::assertNothingSent();
    }

    private function partnerAccount(User $partner): int
    {
        return (int) DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => (string) Str::uuid(), 'created_by' => $partner->id,
            'partner_name' => 'Synthetic authorised platform', 'partner_type' => 'platform', 'status' => 'active',
            'allowed_products' => json_encode(['essentials'], JSON_THROW_ON_ERROR), 'commercial_terms' => '{}',
            'approved_by' => $partner->id, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
