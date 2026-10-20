<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPassportShadowReviewTest extends TestCase
{
    use RefreshDatabase;

    private function references(bool $consented = true, bool $validLender = true): array
    {
        $borrower = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $institution = Institution::create([
            'name' => 'Test merchant lender', 'address' => 'Test road',
            'phone' => '000000000', 'email' => (string) Str::uuid().'@example.test',
            'country' => 'UG', 'status' => $validLender ? 'Active' : 'Suspended',
            'lender_relationship' => 'independent', 'authority_basis' => 'licensed',
            'authority_reference' => 'TEST-LICENCE-NOT-REAL', 'regulator_code' => 'TEST',
        ]);
        $product = LoanProduct::create([
            'name' => 'Test-only merchant stock finance', 'type' => 'Cash',
            'status' => 'Active', 'institution_id' => $institution->id,
            'country' => 'UG', 'currency' => 'UGX',
            'product_category' => 'merchant_stock_finance',
        ]);
        $space = FinancialSpace::create([
            'public_id' => (string) Str::uuid(), 'type' => 'business',
            'name' => 'Synthetic merchant space', 'country' => 'UG',
            'currency' => 'UGX', 'status' => 'active',
            'metadata' => ['stolets_tenant_id' => 'merchant-one'],
        ]);
        FinancialSpaceMembership::create([
            'financial_space_id' => $space->id, 'user_id' => $borrower->id,
            'role' => 'owner', 'status' => 'active', 'joined_at' => now(),
            'approved_at' => now(),
        ]);
        ConsentRecord::create([
            'user_id' => $borrower->id, 'purpose' => 'merchant_finance_assessment',
            'policy_version' => 'merchant-shadow-v1',
            'status' => $consented ? ConsentRecord::STATUS_GRANTED : ConsentRecord::STATUS_REVOKED,
            'granted_at' => now()->subMinute(),
            'revoked_at' => $consented ? null : now(),
            'metadata' => [
                'financial_space_id' => $space->id,
                'stolets_tenant_id' => 'merchant-one',
                'lender_recipient_id' => 'opfin-lender-'.$institution->id,
            ],
        ]);

        return [
            'borrowerUserId' => $borrower->id,
            'loanProductId' => $product->id,
            'financialSpaceId' => $space->id,
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
            'providerOfRecordId' => 'opfin-lender-1',
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
        $references = $this->references();
        $lender = 'opfin-lender-'.LoanProduct::withoutGlobalScopes()->findOrFail($references['loanProductId'])->institution_id;
        Http::fake(['https://stolets.example.test/*' => Http::response($this->packet([
            'providerOfRecordId' => $lender,
        ]), 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));

        $this->postJson('/api/admin/business-passport/shadow-review', $references)
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
            && $request['providerOfRecordId'] === $lender
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

    public function test_absent_customer_consent_or_inactive_lender_denies_before_external_data_call(): void
    {
        $this->enable();
        Http::fake();
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));

        $this->postJson('/api/admin/business-passport/shadow-review', $this->references(false))
            ->assertStatus(503);
        $this->postJson('/api/admin/business-passport/shadow-review', $this->references(true, false))
            ->assertStatus(503);
        Http::assertNothingSent();
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
