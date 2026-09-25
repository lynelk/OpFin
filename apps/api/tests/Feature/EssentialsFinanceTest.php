<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\CreditProfile;
use App\Models\CreditScoreComponent;
use App\Models\EssentialsAccount;
use App\Models\EssentialsBiller;
use App\Models\Institution;
use App\Models\User;
use App\Services\CitoEssentialsLendingClient;
use App\Services\ExternalScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EssentialsFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opfin_cannot_be_configured_as_primary_essentials_lender(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/essentials/lenders', [
            'partner_code' => 'OPFIN',
            'partner_name' => 'OpFin',
            'partner_type' => 'financial_institution',
            'regulatory_evidence' => [
                'licence_number' => 'TEST-001',
                'licence_authority' => 'Test Authority',
            ],
            'status' => 'active',
            'product_code' => 'ESS',
            'product_name' => 'Essentials',
            'product_type' => 'essentials_credit',
            'eligibility_rules' => ['max_limit_minor' => 100000],
            'pricing' => ['term_days' => 90],
            'decision_route' => 'cito',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'OpFin cannot be configured as the primary lender for Essentials.');
    }

    public function test_lender_onboarding_capital_mandate_maker_checker_and_product_activation_are_end_to_end(): void
    {
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $reviewer = User::factory()->create(['role' => User::ROLE_OPERATIONS]);

        $lenderPayload = [
            'partner_code' => 'FLOWBANK',
            'partner_name' => 'Flow Bank Test',
            'partner_type' => 'financial_institution',
            'regulatory_evidence' => [
                'licence_number' => 'FLOW-LIC-001',
                'licence_authority' => 'Test Authority',
            ],
            'status' => 'onboarding',
            'product_code' => 'FLOW-ESS',
            'product_name' => 'Flow Essentials',
            'product_type' => 'essentials_credit',
            'eligibility_rules' => [
                'categories' => ['electricity', 'water', 'rent'],
                'min_score' => 0,
                'min_coverage_percent' => 0,
                'min_limit_minor' => 1,
                'max_limit_minor' => 500000,
            ],
            'pricing' => [
                'term_days' => 90,
                'monthly_interest_rate_percent' => 0,
                'fixed_fee_minor' => 0,
                'fee_percent' => 0,
            ],
            'decision_route' => 'capital_mandate',
        ];

        Sanctum::actingAs($maker);
        $onboarding = $this->postJson('/api/admin/essentials/lenders', $lenderPayload)
            ->assertCreated()
            ->json('data');

        $partnerId = (int) $onboarding['partner_id'];
        $this->assertDatabaseHas('partners', [
            'id' => $partnerId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('partner_products', [
            'id' => (int) $onboarding['partner_product_id'],
            'status' => 'draft',
        ]);

        $mandate = $this->postJson('/api/admin/capital-mandates', [
            'partner_id' => $partnerId,
            'mandate_type' => 'warehouse_line',
            'name' => 'Flow Bank OpFin credit pool',
            'committed_capital_minor' => 1000000,
            'investment_policy' => [
                'categories' => ['essentials', 'salary'],
                'purpose' => 'Third-party funded OpFin credit',
            ],
        ])->assertCreated()->json('data.capital_mandate');

        $mandateId = (int) $mandate['id'];
        $this->postJson('/api/admin/capital-mandates/'.$mandateId.'/review', [
            'status' => 'approved',
        ])->assertStatus(422);

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/admin/capital-mandates/'.$mandateId.'/review', [
            'status' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('data.capital_mandate.status', 'approved');

        Sanctum::actingAs($maker);
        $activation = $lenderPayload;
        $activation['status'] = 'active';
        $activation['funding_pool_id'] = $mandateId;

        $this->postJson('/api/admin/essentials/lenders', $activation)
            ->assertCreated();

        $this->assertDatabaseHas('partner_products', [
            'id' => (int) $onboarding['partner_product_id'],
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('capital_mandates', [
            'id' => $mandateId,
            'partner_id' => $partnerId,
            'status' => 'approved',
        ]);
    }

    public function test_third_party_lender_lines_do_not_stack_into_a_larger_customer_limit(): void
    {
        [$customer, $space] = $this->customerWithCreditProfile(500000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);

        $this->seedMandateLender($admin, 'BANKA', 300000, 90);
        $this->seedMandateLender($admin, 'BANKB', 250000, 90);

        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/essentials/eligibility', ['channel' => 'android'])
            ->assertOk();

        $this->assertCount(2, $response->json('data.lines'));
        $this->assertSame(300000, (int) $response->json('data.overall.overall_available_limit_minor'));
        $this->assertSame(0, (int) $response->json('data.overall.outstanding_minor'));
        $this->assertDatabaseCount('essentials_credit_lines', 2);

        $this->assertSame($space['id'], (int) DB::table('essentials_credit_lines')->first()->financial_space_id);
    }

    public function test_android_quote_rejects_short_term_lender_product_and_allows_compliant_term(): void
    {
        [$customer] = $this->customerWithCreditProfile(200000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $seed = $this->seedMandateLender($admin, 'SHORTBANK', 200000, 30);

        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'android'])->assertOk();

        $account = $this->verifiedElectricityAccount($customer);

        $this->postJson('/api/essentials/quotes', [
            'essentials_account_id' => $account->id,
            'amount_minor' => 50000,
            'channel' => 'android',
        ])->assertStatus(422);

        DB::table('partner_products')->where('id', $seed['product_id'])->update([
            'pricing' => json_encode([
                'term_days' => 90,
                'monthly_interest_rate_percent' => 0,
                'fixed_fee_minor' => 0,
                'fee_percent' => 0,
            ], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);

        $quoteResponse = $this->postJson('/api/essentials/quotes', [
            'essentials_account_id' => $account->id,
            'amount_minor' => 50000,
            'channel' => 'android',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quote.term_days', 90)
            ->assertJsonPath('data.quote.disclosure_snapshot.opfin_role', 'orchestrator_and_servicer_not_primary_lender')
            ->assertJsonPath('data.quote.disclosure_snapshot.cash_disbursement_to_customer', false);

        $this->assertSame('SHORTBANK', $quoteResponse->json('data.quote.disclosure_snapshot.lender.partner_code'));
    }

    public function test_affiliated_essentials_credit_is_hidden_withheld_and_fallback_only_under_admin_strategy(): void
    {
        [$customer] = $this->customerWithCreditProfile(200000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $core = $this->seedMandateLender($admin, 'CORETEST', 200000, 90);
        $lender = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-AUTHORITY-NOT-LIVE', 'regulator_code' => 'TEST', 'name' => 'Core test entity', 'address' => 'Test address', 'phone' => '256700000000', 'email' => 'test@example.org', 'status' => 'Active', 'lender_relationship' => 'affiliated']);
        DB::table('partners')->where('id', $core['partner_id'])->update(['institution_id' => $lender->id]);
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(0, 'data.lines')->assertJsonPath('data.overall.overall_available_limit_minor', 0);
        $account = $this->verifiedElectricityAccount($customer);
        $request = ['essentials_account_id' => $account->id, 'amount_minor' => 50000, 'channel' => 'web'];
        $this->postJson('/api/essentials/quotes', $request)->assertUnprocessable();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/lending-platform/strategy', ['mode' => 'external_first', 'reason' => 'Test affiliated fallback deployment', 'effective_from' => now()->toISOString()])->assertCreated();
        Sanctum::actingAs($customer);
        $quote = $this->postJson('/api/essentials/quotes', $request)->assertCreated()->assertJsonPath('data.quote.lender_partner_id', $core['partner_id'])->json('data.quote');
        $external = $this->seedMandateLender($admin, 'EXTERNALTEST', 200000, 90);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(1, 'data.lines');
        $this->postJson('/api/essentials/quotes', $request)->assertCreated()->assertJsonPath('data.quote.lender_partner_id', $external['partner_id']);
        $this->configureCpay();
        Http::fake();
        $this->postJson('/api/essentials/quotes/'.$quote['id'].'/accept', ['disclosure_hash' => $quote['disclosure_hash'], 'accept_disclosures' => true])->assertStatus(422)->assertJsonPath('message', 'This lender offer is no longer available under the current distribution or credit deployment policy.');
        Http::assertNothingSent();
        $this->assertDatabaseCount('essentials_advances', 0);
        $this->assertDatabaseHas('capital_mandates', ['id' => $core['pool_id'], 'reserved_capital_minor' => 0]);
    }

    public function test_withheld_or_unneeded_affiliate_is_not_contacted_for_credit_decisions(): void
    {
        [$customer] = $this->customerWithCreditProfile(200000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $affiliateProduct = $this->seedCitoLender('AFFILIATE-CITO', 200000, 90);
        $lender = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-ONLY', 'regulator_code' => 'TEST', 'name' => 'Affiliate Test', 'address' => 'Test', 'phone' => '256700000000', 'email' => 'affiliate@example.org', 'status' => 'Active', 'lender_relationship' => 'affiliated']);
        DB::table('partners')->where('id', DB::table('partner_products')->where('id', $affiliateProduct)->value('partner_id'))->update(['institution_id' => $lender->id]);
        $client = $this->mock(CitoEssentialsLendingClient::class);
        $client->shouldReceive('drawdownConfigured')->andReturn(true);
        $client->shouldNotReceive('requestCreditLine');
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(0, 'data.lines');
        $this->assertDatabaseCount('essentials_credit_lines', 0);
        $this->seedMandateLender($admin, 'INDEPENDENT', 200000, 90);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/lending-platform/strategy', ['mode' => 'external_first', 'reason' => 'Test independent first deployment', 'effective_from' => now()->toISOString()])->assertCreated();
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(1, 'data.lines');
        $this->assertDatabaseMissing('essentials_credit_lines', ['partner_product_id' => $affiliateProduct]);
    }

    public function test_channel_restriction_removes_existing_lines_from_refresh_and_summary(): void
    {
        [$customer] = $this->customerWithCreditProfile(200000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $seed = $this->seedMandateLender($admin, 'SCOPED', 200000, 90);
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(1, 'data.lines');
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/lending-platform/distribution', ['channel' => 'play_store', 'country' => 'UG', 'product_category' => 'personal_loan', 'partner_product_id' => $seed['product_id'], 'availability' => 'unavailable', 'reason' => 'Test channel suspension', 'source_reference' => 'TEST', 'effective_from' => now()->toISOString()])->assertCreated();
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'android'])->assertOk()->assertJsonCount(0, 'data.lines')->assertJsonPath('data.overall.overall_available_limit_minor', 0);
        $this->getJson('/api/essentials?channel=android')->assertOk()->assertJsonPath('data.overall_available_limit_minor', 0);
        $this->getJson('/api/essentials?channel=web')->assertOk()->assertJsonPath('data.overall_available_limit_minor', 200000);
        $this->getJson('/api/essentials?channel=unknown')->assertUnprocessable();
    }

    public function test_affiliate_fallback_is_evaluated_for_amount_after_independent_discovery(): void
    {
        [$customer] = $this->customerWithCreditProfile(200000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $this->seedMandateLender($admin, 'SMALL-INDEPENDENT', 20000, 90);
        $core = $this->seedMandateLender($admin, 'LARGER-AFFILIATE', 200000, 90);
        $lender = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-ONLY', 'regulator_code' => 'TEST', 'name' => 'Affiliate Test', 'address' => 'Test', 'phone' => '256700000000', 'email' => 'affiliate@example.org', 'status' => 'Active', 'lender_relationship' => 'affiliated']);
        DB::table('partners')->where('id', $core['partner_id'])->update(['institution_id' => $lender->id]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/lending-platform/strategy', ['mode' => 'external_first', 'reason' => 'Test independent first deployment', 'effective_from' => now()->toISOString()])->assertCreated();
        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'web'])->assertOk()->assertJsonCount(1, 'data.lines');
        $this->assertDatabaseMissing('essentials_credit_lines', ['partner_product_id' => $core['product_id']]);
        $account = $this->verifiedElectricityAccount($customer);
        $this->postJson('/api/essentials/quotes', ['essentials_account_id' => $account->id, 'amount_minor' => 80000, 'channel' => 'web'])->assertCreated()->assertJsonPath('data.quote.lender_partner_id', $core['partner_id']);
    }

    public function test_embedded_platform_requires_customer_permission_before_it_can_write_essentials_data(): void
    {
        [$customer, $space] = $this->customerWithCreditProfile(100000);
        $partnerUser = User::factory()->create(['role' => User::ROLE_PARTNER_API]);

        $partnerAccountId = DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => (string) Str::uuid(),
            'created_by' => $partnerUser->id,
            'partner_name' => 'Stolets Test',
            'partner_type' => 'platform',
            'status' => 'active',
            'allowed_products' => json_encode(['essentials'], JSON_THROW_ON_ERROR),
            'commercial_terms' => json_encode([], JSON_THROW_ON_ERROR),
            'approved_by' => $partnerUser->id,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $biller = EssentialsBiller::query()->where('code', 'UEDCL')->firstOrFail();

        Sanctum::actingAs($partnerUser);
        $this->postJson('/api/partner/essentials/customers/'.$customer->id.'/accounts', [
            'partner_account_id' => $partnerAccountId,
            'biller_id' => $biller->id,
            'financial_space_id' => $space['id'],
            'account_reference' => '04200000001',
            'source_platform' => 'Stolets',
            'customer_consent_reference' => 'STOLETS-CONSENT-1',
        ])->assertForbidden();

        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/partner-authorisations', [
            'partner_account_id' => $partnerAccountId,
            'financial_space_id' => $space['id'],
            'scopes' => ['account_write', 'quote_create', 'eligibility', 'status_read'],
            'valid_days' => 30,
        ])->assertCreated();

        Sanctum::actingAs($partnerUser);
        $this->postJson('/api/partner/essentials/customers/'.$customer->id.'/accounts', [
            'partner_account_id' => $partnerAccountId,
            'biller_id' => $biller->id,
            'financial_space_id' => $space['id'],
            'account_reference' => '04200000001',
            'source_platform' => 'Stolets',
            'customer_consent_reference' => 'STOLETS-CONSENT-1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.account.verification_status', 'pending');

        $this->assertDatabaseHas('essentials_accounts', [
            'user_id' => $customer->id,
            'biller_id' => $biller->id,
        ]);

        Sanctum::actingAs($customer);
        $authorisationId = (int) DB::table('essentials_partner_authorisations')
            ->where('user_id', $customer->id)
            ->where('partner_account_id', $partnerAccountId)
            ->where('status', 'active')
            ->value('id');
        $this->deleteJson('/api/essentials/partner-authorisations/'.$authorisationId)->assertOk();

        Sanctum::actingAs($partnerUser);
        $this->postJson('/api/partner/essentials/customers/'.$customer->id.'/eligibility', [
            'partner_account_id' => $partnerAccountId,
            'financial_space_id' => $space['id'],
            'channel' => 'partner',
        ])->assertStatus(422);
    }

    public function test_purpose_bound_settlement_and_repayment_restore_lender_capital_and_financial_space_obligation(): void
    {
        [$customer] = $this->customerWithCreditProfile(100000);
        $admin = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $seed = $this->seedMandateLender($admin, 'CAPBANK', 100000, 90);

        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'android'])->assertOk();
        $account = $this->verifiedElectricityAccount($customer);

        $quote = $this->postJson('/api/essentials/quotes', [
            'essentials_account_id' => $account->id,
            'amount_minor' => 50000,
            'channel' => 'android',
        ])->assertCreated()->json('data.quote');

        // A later catalogue change must not reprice the immutable quoted offer.
        DB::table('partner_products')->where('id', $seed['product_id'])->update(['pricing' => json_encode(['term_days' => 7, 'monthly_interest_rate_percent' => 0, 'fixed_fee_minor' => 0, 'fee_percent' => 0], JSON_THROW_ON_ERROR)]);

        $this->configureCpay();
        Http::fake([
            'https://cpay.test/v1/bills/pay' => Http::response([
                'status' => 'SUCCESS',
                'providerReference' => 'CPAY-BILL-001',
                'token' => 'sensitive-token-must-not-be-stored',
            ], 200),
            'https://cpay.test/v1/lender-repayments' => Http::response([
                'status' => 'SUCCESS',
                'providerReference' => 'CPAY-REPAY-001',
            ], 200),
        ]);

        $advanceResponse = $this->postJson('/api/essentials/quotes/'.$quote['id'].'/accept', [
            'disclosure_hash' => $quote['disclosure_hash'],
            'accept_disclosures' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.advance.status', 'active');

        $advanceId = (int) $advanceResponse->json('data.advance.id');

        $this->assertDatabaseHas('capital_mandates', [
            'id' => $seed['pool_id'],
            'reserved_capital_minor' => 0,
            'deployed_capital_minor' => 50000,
        ]);
        $advance = DB::table('essentials_advances')->where('id', $advanceId)->first();
        $this->assertNotNull($advance->financial_obligation_id);
        $this->assertDatabaseHas('financial_obligations', [
            'id' => $advance->financial_obligation_id,
            'outstanding_amount_minor' => 50000,
            'status' => 'open',
        ]);
        $this->assertStringNotContainsString(
            'sensitive-token-must-not-be-stored',
            (string) DB::table('essentials_advances')->where('id', $advanceId)->value('fulfilment_payload')
        );

        $this->postJson('/api/essentials/advances/'.$advanceId.'/repay', [
            'amount_minor' => 50000,
            'idempotency_key' => 'ESS-REPAY-TEST-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.repayment.status', 'successful')
            ->assertJsonPath('data.repayment.principal_applied_minor', 50000);

        $this->assertDatabaseHas('essentials_advances', [
            'id' => $advanceId,
            'status' => 'settled',
            'outstanding_minor' => 0,
            'principal_outstanding_minor' => 0,
        ]);
        $this->assertDatabaseHas('financial_obligations', [
            'id' => $advance->financial_obligation_id,
            'outstanding_amount_minor' => 0,
            'status' => 'settled',
        ]);
        $this->assertDatabaseHas('capital_mandates', [
            'id' => $seed['pool_id'],
            'deployed_capital_minor' => 0,
        ]);
        $this->assertDatabaseHas('essentials_credit_lines', [
            'partner_product_id' => $seed['product_id'],
            'available_limit_minor' => 100000,
            'outstanding_minor' => 0,
        ]);

        Http::assertSent(function ($request) use ($customer) {
            if (! str_ends_with($request->url(), '/v1/lender-repayments')) {
                return false;
            }

            return (string) data_get($request->data(), 'payerMsisdn') === (string) $customer->phone
                && data_get($request->data(), 'purpose') === 'opfin_essentials_repayment';
        });
    }

    public function test_cito_lender_drawdown_is_confirmed_before_cpay_pays_the_provider(): void
    {
        [$customer] = $this->customerWithCreditProfile(100000);
        $this->seedCitoLender('CITOBANK', 100000, 90);
        $this->configureCito();
        $this->configureCpay();

        Http::fake([
            'https://cito.test/v1/essentials/credit-lines' => Http::response([
                'status' => 'APPROVED',
                'approvedLimitMinor' => 100000,
                'providerReference' => 'CITO-LINE-001',
            ], 200),
            'https://cito.test/v1/essentials/drawdowns' => Http::response([
                'status' => 'AUTHORISED',
                'providerReference' => 'CITO-FUND-001',
            ], 200),
            'https://cpay.test/v1/bills/pay' => Http::response([
                'status' => 'SUCCESS',
                'providerReference' => 'CPAY-BILL-CITO-001',
            ], 200),
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/essentials/eligibility', ['channel' => 'android'])
            ->assertOk()
            ->assertJsonPath('data.lines.0.decision_route', 'cito');

        $account = $this->verifiedElectricityAccount($customer);
        $quote = $this->postJson('/api/essentials/quotes', [
            'essentials_account_id' => $account->id,
            'amount_minor' => 50000,
            'channel' => 'android',
        ])->assertCreated()->json('data.quote');

        $advance = $this->postJson('/api/essentials/quotes/'.$quote['id'].'/accept', [
            'disclosure_hash' => $quote['disclosure_hash'],
            'accept_disclosures' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.advance.status', 'active')
            ->assertJsonPath('data.advance.lender_funding_reference', 'CITO-FUND-001')
            ->json('data.advance');

        $this->assertNotNull($advance['financial_obligation_id']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/v1/bills/pay')
                && data_get($request->data(), 'lenderFundingReference') === 'CITO-FUND-001';
        });
    }

    public function test_gnugrid_direct_crb_route_is_blocked_and_requires_cito(): void
    {
        $user = User::factory()->create();
        config([
            'opfin.integrations.external_service_route' => 'direct',
            'opfin.integrations.direct_provider_fallback_enabled' => true,
            'services.crb.provider' => 'gnuGrid CRB',
            'services.crb.base_url' => 'https://gnugrid-direct.test',
            'services.crb.account' => 'test',
            'services.crb.password' => 'secret',
            'services.cito.base_url' => '',
        ]);
        Http::fake();

        $result = app(ExternalScoringService::class)->refresh($user);
        $crb = $result['crb'];

        $this->assertSame(CreditScoreComponent::STATUS_UNAVAILABLE, $crb->status);
        $this->assertContains('GNUGRID_REQUIRES_CITO', $crb->reason_codes);
        Http::assertNothingSent();
    }

    private function customerWithCreditProfile(int $availableMinor): array
    {
        DB::table('financial_policies')->insert([
            'code' => 'TEST-UG-REGULATORY-PRICING',
            'policy_type' => 'regulatory_pricing',
            'jurisdiction_country' => 'UG',
            'licence_class' => null,
            'product_scope' => null,
            'version' => 1,
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'rules' => json_encode([
                'max_rate_percent' => 2.8,
                'rate_cycle' => 'monthly',
                'fee_caps' => [
                    'access_fee_percent' => 3,
                    'disbursement_fee_minor' => 2000,
                ],
            ], JSON_THROW_ON_ERROR),
            'source_reference' => 'test-policy',
            'source_url' => null,
            'approved_by' => null,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        $spaceId = DB::table('financial_spaces')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'type' => 'personal',
            'name' => 'My money',
            'country' => 'UG',
            'currency' => 'UGX',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $customer->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $space = ['id' => $spaceId, 'type' => 'personal', 'name' => 'My money'];
        Sanctum::actingAs($customer);

        CreditProfile::create([
            'user_id' => $customer->id,
            'status' => CreditProfile::STATUS_READY,
            'composite_score' => 80,
            'band' => 'Strong',
            'coverage_percent' => 100,
            'credit_limit_minor' => $availableMinor,
            'current_exposure_minor' => 0,
            'available_to_borrow_minor' => $availableMinor,
            'amount_due_minor' => 0,
            'total_outstanding_minor' => 0,
            'model_version' => 'test-v1',
            'component_breakdown' => [],
            'reason_codes' => [],
            'customer_explanations' => [],
            'scored_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'test-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'api',
            'granted_at' => now(),
        ]);

        return [$customer, $space];
    }

    private function seedMandateLender(User $admin, string $code, int $committedMinor, int $termDays): array
    {
        $partnerId = DB::table('partners')->insertGetId([
            'code' => $code,
            'name' => $code.' Financial Institution',
            'partner_type' => 'financial_institution',
            'country' => 'UG',
            'status' => 'active',
            'adapter_key' => 'capital_mandate',
            'regulatory_evidence' => json_encode([
                'licence_number' => $code.'-LIC',
                'licence_authority' => 'Test Authority',
            ], JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['essentials_enabled' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $poolId = DB::table('capital_mandates')->insertGetId([
            'reference' => (string) Str::uuid(),
            'owner_user_id' => $admin->id,
            'partner_id' => $partnerId,
            'mandate_type' => 'institutional_essentials',
            'name' => $code.' Essentials Pool',
            'committed_capital_minor' => $committedMinor,
            'deployed_capital_minor' => 0,
            'reserved_capital_minor' => 0,
            'status' => 'active',
            'investment_policy' => json_encode(['categories' => ['electricity', 'water', 'rent']], JSON_THROW_ON_ERROR),
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('partner_products')->insertGetId([
            'partner_id' => $partnerId,
            'code' => $code.'-ESS',
            'name' => $code.' Essentials',
            'product_type' => 'essentials_credit',
            'status' => 'active',
            'country' => 'UG',
            'currency' => 'UGX',
            'eligibility_rules' => json_encode([
                'categories' => ['electricity', 'water', 'internet', 'television', 'energy', 'rent'],
                'min_score' => 0,
                'min_coverage_percent' => 0,
                'min_limit_minor' => 1,
                'max_limit_minor' => $committedMinor,
                'line_valid_days' => 30,
            ], JSON_THROW_ON_ERROR),
            'pricing' => json_encode([
                'term_days' => $termDays,
                'monthly_interest_rate_percent' => 0,
                'fixed_fee_minor' => 0,
                'fee_percent' => 0,
            ], JSON_THROW_ON_ERROR),
            'disclosures' => json_encode(['lender_of_record' => $code.' Financial Institution'], JSON_THROW_ON_ERROR),
            'integration_config' => json_encode([
                'decision_route' => 'capital_mandate',
                'funding_pool_id' => $poolId,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['partner_id' => $partnerId, 'pool_id' => $poolId, 'product_id' => $productId];
    }

    private function seedCitoLender(string $code, int $maxLimitMinor, int $termDays): int
    {
        $partnerId = DB::table('partners')->insertGetId([
            'code' => $code,
            'name' => $code.' Financial Institution',
            'partner_type' => 'financial_institution',
            'country' => 'UG',
            'status' => 'active',
            'adapter_key' => 'cito',
            'regulatory_evidence' => json_encode([
                'licence_number' => $code.'-LIC',
                'licence_authority' => 'Test Authority',
            ], JSON_THROW_ON_ERROR),
            'metadata' => json_encode(['essentials_enabled' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('partner_products')->insertGetId([
            'partner_id' => $partnerId,
            'code' => $code.'-ESS',
            'name' => $code.' Essentials',
            'product_type' => 'essentials_credit',
            'status' => 'active',
            'country' => 'UG',
            'currency' => 'UGX',
            'eligibility_rules' => json_encode([
                'categories' => ['electricity', 'water', 'internet', 'television', 'energy', 'rent'],
                'min_score' => 0,
                'min_coverage_percent' => 0,
                'min_limit_minor' => 1,
                'max_limit_minor' => $maxLimitMinor,
                'line_valid_days' => 30,
            ], JSON_THROW_ON_ERROR),
            'pricing' => json_encode([
                'term_days' => $termDays,
                'monthly_interest_rate_percent' => 0,
                'fixed_fee_minor' => 0,
                'fee_percent' => 0,
            ], JSON_THROW_ON_ERROR),
            'disclosures' => json_encode(['lender_of_record' => $code.' Financial Institution'], JSON_THROW_ON_ERROR),
            'integration_config' => json_encode(['decision_route' => 'cito'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureCito(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);
        $this->assertTrue(openssl_pkey_export($resource, $privateKey));

        config([
            'services.cito.base_url' => 'https://cito.test',
            'services.cito.merchant_number' => 'OPFIN-CITO-TEST',
            'services.cito.private_key' => $privateKey,
            'services.cito.environment' => 'SANDBOX',
            'services.cito.essentials_lending_path' => '/v1/essentials/credit-lines',
            'services.cito.essentials_drawdown_path' => '/v1/essentials/drawdowns',
            'services.cito.essentials_drawdown_status_path' => '/v1/essentials/drawdowns/status',
            'services.cito.essentials_drawdown_release_path' => '/v1/essentials/drawdowns/release',
        ]);
    }

    private function verifiedElectricityAccount(User $customer): EssentialsAccount
    {
        $biller = EssentialsBiller::query()->where('code', 'UEDCL')->firstOrFail();
        $spaceId = (int) DB::table('financial_space_memberships')
            ->where('user_id', $customer->id)
            ->where('status', 'active')
            ->value('financial_space_id');
        $reference = '04200000001';

        return EssentialsAccount::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $customer->id,
            'financial_space_id' => $spaceId,
            'biller_id' => $biller->id,
            'account_reference' => $reference,
            'account_reference_hash' => hash('sha256', $reference),
            'verification_status' => 'verified',
            'provider_reference' => 'UEDCL-TEST-ACCOUNT',
            'verified_at' => now(),
            'metadata' => [],
        ]);
    }

    private function configureCpay(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);
        $this->assertTrue(openssl_pkey_export($resource, $privateKey));

        config([
            'services.cpay.base_url' => 'https://cpay.test',
            'services.cpay.merchant_number' => 'OPFIN-TEST',
            'services.cpay.private_key' => $privateKey,
            'services.cpay.environment' => 'SANDBOX',
            'services.cpay.country' => 'UG',
            'services.cpay.currency' => 'UGX',
            'services.cpay.bill_payment_path' => '/v1/bills/pay',
            'services.cpay.beneficiary_payment_path' => '/v1/beneficiaries/pay',
            'services.cpay.lender_repayment_path' => '/v1/lender-repayments',
            'services.cpay.transaction_status_path' => '/v1/transactions/status',
        ]);
    }
}
