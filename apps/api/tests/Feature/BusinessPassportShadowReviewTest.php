<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPassportShadowReviewTest extends TestCase
{
    use RefreshDatabase;

    private function references(): array
    {
        return [
            'tenantId' => 'merchant-one',
            'consentGrantId' => 'grant-one',
            'passportShareId' => 'share-one',
            'featureSnapshotId' => 'feature-one',
        ];
    }

    private function enable(): void
    {
        config()->set('services.stolets_shadow', [
            'enabled' => true,
            'base_url' => 'https://stolets.example.test',
            'partner_token' => str_repeat('x', 40),
        ]);
    }

    private function packet(array $overrides = []): array
    {
        return array_replace_recursive([
            'contractVersion' => 'opfin-partner-shadow-v1',
            'source' => 'stolets', 'purpose' => 'credit_assessment',
            'recipient' => 'opfin', 'evidenceOnly' => true,
            'creditOrPaymentAuthorised' => false,
            'tenantId' => 'merchant-one',
            'consentGrantId' => 'grant-one',
            'sharePackageId' => 'share-one',
            'featureSnapshotId' => 'feature-one',
            'sourcePeriodUntil' => now()->subDay()->toIso8601String(),
            'expiresAt' => now()->addDay()->toIso8601String(),
            'metrics' => [
                'currency' => 'UGX', 'netSalesMinor' => 850000,
                'refundsMinor' => 15000, 'purchaseOrderValueMinor' => 200000,
                'transactionCount' => 24, 'uniqueTradingDays' => 14,
                'dataCoverageDays' => 60, 'dataQualityBps' => 9200,
                'reconciliationBps' => 9700,
                'expectedBaseGrossCashGenerationMinor' => 180000,
                'expectedDownsideGrossCashGenerationMinor' => 100000,
                'expectedInventoryCycleDays' => 20,
            ],
        ], $overrides);
    }

    public function test_shadow_passport_evidence_is_real_partner_scoped_but_never_a_credit_decision(): void
    {
        $this->enable();
        Http::fake(['https://stolets.example.test/*' => Http::response($this->packet(), 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));

        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertOk()
            ->assertJsonPath('data.status', 'shadow_evidence_received')
            ->assertJsonPath('data.decision', 'manual_review')
            ->assertJsonPath('data.execution_authorised', false)
            ->assertJsonPath('data.existing_credit_policy_unchanged', true)
            ->assertJsonPath('data.probability_of_default', null)
            ->assertJsonPath('data.verified_disposable_cash_flow_minor', null)
            ->assertJsonPath('data.approved_loan_amount_minor', null)
            ->assertJsonPath('data.indicators.netSalesMinor', 850000);

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://stolets.example.test/v1/partner-evidence/opfin/credit-shadow'
            && $request->hasHeader('Authorization', 'Bearer '.str_repeat('x', 40))
            && $request['tenantId'] === 'merchant-one'
        );
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_unauthorised_user_or_disabled_shadow_feature_cannot_fetch_merchant_data(): void
    {
        $this->enable();
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertForbidden();
        Http::assertNothingSent();

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        config()->set('services.stolets_shadow.enabled', false);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_partner_failure_replay_mismatch_and_unverified_data_never_approve(): void
    {
        $this->enable();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        Http::fake(['https://stolets.example.test/*' => Http::response(['error' => 'revoked'], 403)]);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertStatus(503);

        Http::fake(['https://stolets.example.test/*' => Http::response($this->packet([
            'consentGrantId' => 'other-grant',
        ]), 200)]);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertStatus(503);

        Http::fake(['https://stolets.example.test/*' => Http::response($this->packet([
            'creditOrPaymentAuthorised' => true,
        ]), 200)]);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertStatus(503);
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_stale_and_absent_funding_data_remain_unavailable(): void
    {
        $this->enable();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        Http::fake(['https://stolets.example.test/*' => Http::response($this->packet([
            'sourcePeriodUntil' => now()->subDays(8)->toIso8601String(),
        ]), 200)]);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references())
            ->assertStatus(503);
    }
}
