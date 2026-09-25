<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use App\Services\CreditDistributionService;
use App\Services\CreditTermGovernanceService;
use App\Services\FundingPoolService;
use App\Services\PlatformCreditRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LendingPlatformConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function route(string $relationship = 'independent', int $days = 30): array
    {
        $lender = Institution::create(['authority_basis' => 'licensed', 'authority_reference' => 'TEST-AUTHORITY-NOT-LIVE', 'regulator_code' => 'TEST', 'name' => $relationship === 'affiliated' ? 'Core Synergies test lender' : 'Independent test lender', 'address' => 'Test address', 'phone' => '256700000000', 'email' => fake()->unique()->safeEmail(), 'lender_relationship' => $relationship, 'status' => 'Active']);
        $product = LoanProduct::create(['name' => 'Test credit', 'type' => 'Cash', 'status' => 'Active', 'institution_id' => $lender->id]);
        $term = LoanProductTerm::create(['loan_product_id' => $product->id, 'interest_rate' => 0, 'interest_type' => 'Flat', 'interest_cycle' => 'monthly', 'repayment_frequency' => 'monthly', 'duration' => $days, 'status' => 'Active']);

        return [$lender->fresh(), $product->fresh(), $term->fresh()];
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function strategy(string $mode, ?int $cap = null): void
    {
        $this->postJson('/api/admin/lending-platform/strategy', ['mode' => $mode, 'reason' => 'Documented deployment decision for testing', 'effective_from' => now()->toISOString(), 'max_affiliated_loan_minor' => $cap])->assertCreated();
    }

    public function test_short_product_is_preserved_while_channel_availability_changes(): void
    {
        [, $product, $term] = $this->route('independent', 7);
        $policy = app(CreditDistributionService::class);
        $this->assertTrue($policy->assess($product, $term, 'web')['available']);
        $this->assertFalse($policy->assess($product, $term, 'play_store')['available']);
        $this->assertSame('CHANNEL_REVIEW_REQUIRED', $policy->assess($product, $term, 'huawei_appgallery')['code']);
        $this->assertDatabaseHas('loan_product_terms', ['id' => $term->id, 'duration' => 7, 'status' => 'Active']);
        config(['credit_distribution.defaults.play_store.personal_loan.min_duration_days' => 7]);
        $this->assertTrue($policy->assess($product, $term, 'play_store')['available']);
    }

    public function test_policy_revision_is_scoped_to_lender_product_and_channel_and_expires(): void
    {
        [$lender, $product, $term] = $this->route('independent', 7);
        [, $otherProduct, $otherTerm] = $this->route('independent', 7);
        $this->admin();
        $this->postJson('/api/admin/lending-platform/distribution', ['channel' => 'huawei_appgallery', 'country' => 'UG', 'product_category' => 'personal_loan', 'institution_id' => $lender->id, 'loan_product_id' => $product->id, 'availability' => 'available', 'min_duration_days' => 7, 'reason' => 'Test-only reviewed channel evidence', 'source_reference' => 'TEST-EVIDENCE-NOT-LIVE', 'effective_from' => now()->toISOString(), 'effective_to' => now()->addHour()->toISOString()])->assertCreated()->assertJsonPath('data.rule.version', 1);
        $policy = app(CreditDistributionService::class);
        $this->assertTrue($policy->assess($product, $term, 'huawei_appgallery')['available']);
        $this->assertFalse($policy->assess($otherProduct, $otherTerm, 'huawei_appgallery')['available']);
        $this->assertFalse($policy->assess($product, $term, 'play_store')['available']);
        $this->travel(2)->hours();
        $this->assertFalse($policy->assess($product, $term, 'huawei_appgallery')['available']);
    }

    public static function applicationTimezones(): array
    {
        return [['UTC'], ['Africa/Kampala'], ['America/New_York']];
    }

    #[DataProvider('applicationTimezones')]
    public function test_policy_and_strategy_intervals_preserve_the_submitted_instant(string $timezone): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        try {
            $this->travelTo(Carbon::parse('2026-09-25T12:00:00Z')->setTimezone($timezone));
            [$lender, $product, $term] = $this->route('affiliated', 7);
            $this->admin();
            $interval = ['effective_from' => '2026-09-25T13:00:00Z', 'effective_to' => '2026-09-25T17:00:00+03:00'];
            $this->postJson('/api/admin/lending-platform/strategy', [
                ...$interval, 'mode' => 'affiliated_first', 'reason' => 'Test-only scheduled credit deployment',
            ])->assertCreated();
            $this->postJson('/api/admin/lending-platform/distribution', [
                ...$interval, 'channel' => 'huawei_appgallery', 'country' => 'UG', 'product_category' => 'personal_loan',
                'institution_id' => $lender->id, 'loan_product_id' => $product->id, 'availability' => 'available',
                'min_duration_days' => 7, 'reason' => 'Test-only scheduled channel evidence', 'source_reference' => 'TEST-NOT-LIVE',
            ])->assertCreated();
            $policy = app(CreditDistributionService::class);
            $routing = app(PlatformCreditRoutingService::class);
            $this->assertFalse($policy->assess($product, $term, 'huawei_appgallery')['available']);
            $this->assertSame('withhold', $routing->strategy()['mode']);

            $this->travel(1)->hours();
            $this->assertTrue($policy->assess($product, $term, 'huawei_appgallery')['available']);
            $this->assertSame('affiliated_first', $routing->strategy()['mode']);

            $this->travel(1)->hours();
            $this->assertFalse($policy->assess($product, $term, 'huawei_appgallery')['available']);
            $this->assertSame('withhold', $routing->strategy()['mode']);
        } finally {
            $this->travelBack();
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_unknown_channel_and_cross_lender_policy_scope_are_rejected(): void
    {
        [$lender] = $this->route();
        [, $product] = $this->route();
        $this->admin();
        $data = ['channel' => 'web', 'country' => 'UG', 'product_category' => 'personal_loan', 'institution_id' => $lender->id, 'loan_product_id' => $product->id, 'availability' => 'available', 'reason' => 'Test policy authority', 'source_reference' => 'TEST', 'effective_from' => now()->toISOString()];
        $this->postJson('/api/admin/lending-platform/distribution', $data)->assertUnprocessable();
        $this->getJson('/api/credit/options?distribution_channel=anything')->assertUnprocessable();
    }

    public function test_external_first_uses_affiliated_credit_only_when_independent_routes_cannot_serve_request(): void
    {
        [, $affiliate] = $this->route('affiliated');
        [, $external] = $this->route();
        $external->update(['max_amount_minor' => 50000]);
        $this->admin();
        $this->strategy('external_first');
        $router = app(PlatformCreditRoutingService::class);
        $this->assertSame($external->id, $router->options('web', 'UG', 20000)->first()['product']->id);
        $this->assertFalse($router->options('web', 'UG', 20000)->contains(fn ($row) => $row['product']->id === $affiliate->id));
        $this->assertSame($affiliate->id, $router->options('web', 'UG', 80000)->first()['product']->id);
        $this->getJson('/api/credit/options?distribution_channel=web')->assertOk()->assertJsonPath('data.options.0.loan_product_id', $external->id);
        $this->getJson('/api/credit/options?distribution_channel=web&amount_minor=80000&reason=Emergency')->assertOk()->assertJsonPath('data.options.0.loan_product_id', $affiliate->id);
        $external->update(['borrower_purposes' => ['Education']]);
        $this->getJson('/api/credit/options?distribution_channel=web&amount_minor=20000&reason=Emergency')->assertOk()->assertJsonPath('data.options.0.loan_product_id', $affiliate->id);
    }

    public function test_withhold_priority_cap_and_expiry_control_new_affiliated_origination(): void
    {
        [, $affiliate, $term] = $this->route('affiliated');
        [, $external] = $this->route();
        $this->admin();
        $router = app(PlatformCreditRoutingService::class);
        $this->assertSame($external->id, $router->options('web', 'UG', 20000)->first()['product']->id);
        $this->strategy('affiliated_first', 50000);
        $this->assertSame($affiliate->id, $router->options('web', 'UG', 20000)->first()['product']->id);
        $this->assertSame($external->id, $router->options('web', 'UG', 80000)->first()['product']->id);
        $this->strategy('withhold');
        $this->expectException(InvalidArgumentException::class);
        $router->assertOrigination(new LoanApplication(['loan_product_id' => $affiliate->id, 'loan_product_term_id' => $term->id, 'institution_id' => $affiliate->institution_id, 'amount' => 20000, 'reason' => 'Emergency', 'distribution_channel' => 'web']));
    }

    public function test_only_platform_admin_can_set_strategy_and_delegate_access(): void
    {
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($operations);
        $this->getJson('/api/admin/lending-platform')->assertForbidden();
        $this->postJson('/api/admin/lending-platform/strategy', [])->assertForbidden();
        $this->admin();
        $this->patchJson('/api/admin/lending-platform/access/'.$operations->id, ['enabled' => true])->assertOk();
        Sanctum::actingAs($operations->fresh());
        $this->getJson('/api/admin/lending-platform')->assertOk();
        $this->postJson('/api/admin/lending-platform/strategy', [])->assertForbidden();
        $this->postJson('/api/admin/lending-platform/distribution', [])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => 'lending_platform.access.changed']);
    }

    public function test_affiliated_lender_is_managed_without_creating_a_partner_login_or_claiming_a_licence(): void
    {
        $this->admin();
        $usersBefore = User::count();
        $response = $this->postJson('/api/admin/lending-platform/institutions', ['name' => 'Core Synergies test entity', 'country' => 'UG', 'address' => 'Test only', 'phone' => '256700000000', 'email' => 'test@example.org', 'lender_relationship' => 'affiliated', 'status' => 'Inactive', 'authority_basis' => 'pending', 'rate_change_approval_required' => true])->assertCreated();
        $lender = Institution::findOrFail($response->json('data.institution.id'));
        $this->assertNull($lender->lenderDisclosure()['authority_reference']);
        $this->assertSame('infrastructure_and_orchestration', $lender->lenderDisclosure()['opfin_role']);
        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseHas('partners', ['institution_id' => $lender->id, 'code' => 'LENDER-'.$lender->id]);
    }

    public function test_non_umra_lender_can_apply_its_own_rate_change_requirements(): void
    {
        [$lender, , $term] = $this->route();
        $lender->update(['regulator_code' => 'TEST-OTHER', 'rate_change_approval_required' => false]);
        $maker = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $checker = $this->admin();
        $service = app(CreditTermGovernanceService::class);
        $change = $service->requestChange($term->fresh(), $maker, ['interest_rate' => 1], 'Test lender repricing');
        $service->approve($change->id, $checker, null, null);
        $updated = $service->apply($change->id, $checker);
        $this->assertSame(1.0, (float) $updated->interest_rate);
        $this->assertNull($updated->umra_interest_approval_reference);
    }

    public function test_platform_managed_affiliate_funding_requires_delegated_checker_and_correct_lender(): void
    {
        $maker = $this->admin();
        $response = $this->postJson('/api/admin/lending-platform/institutions', ['name' => 'Core Synergies test entity', 'country' => 'UG', 'address' => 'Test address', 'phone' => '256700000000', 'email' => 'test@example.org', 'lender_relationship' => 'affiliated', 'status' => 'Active', 'authority_basis' => 'licensed', 'regulator_code' => 'TEST', 'authority_reference' => 'TEST-NOT-A-LIVE-LICENCE', 'rate_change_approval_required' => true])->assertCreated();
        $lender = Institution::findOrFail($response->json('data.institution.id'));
        $partnerId = DB::table('partners')->where('institution_id', $lender->id)->value('id');
        $mandate = $this->postJson('/api/admin/capital-mandates', ['partner_id' => $partnerId, 'mandate_type' => 'warehouse_line', 'name' => 'Test Core pool', 'committed_capital_minor' => 100000, 'investment_policy' => ['purpose' => 'Test-only funding']])->assertCreated()->json('data.capital_mandate');
        $this->postJson('/api/admin/capital-mandates/'.$mandate['id'].'/review', ['status' => 'approved'])->assertUnprocessable();
        $checker = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        Sanctum::actingAs($checker);
        $this->postJson('/api/admin/capital-mandates/'.$mandate['id'].'/review', ['status' => 'approved'])->assertForbidden();
        Sanctum::actingAs($maker);
        $this->patchJson('/api/admin/lending-platform/access/'.$checker->id, ['enabled' => true])->assertOk();
        Sanctum::actingAs($checker->fresh());
        $this->postJson('/api/admin/capital-mandates/'.$mandate['id'].'/review', ['status' => 'approved'])->assertOk();
        $funding = app(FundingPoolService::class);
        $funding->validateSelection($mandate['id'], 50000);
        $funding->validateLender($mandate['id'], $lender);
        [$other] = $this->route();
        $this->expectException(InvalidArgumentException::class);
        $funding->validateLender($mandate['id'], $other);
    }

    public function test_configured_daily_and_fortnightly_terms_are_persisted_without_store_tenure_constraints(): void
    {
        [, $product] = $this->route();
        $this->admin();
        $this->postJson('/api/admin/lending-platform/products/'.$product->id.'/terms', ['duration' => 14, 'interest_rate' => 0, 'interest_type' => 'reducing_balance', 'interest_cycle' => 'daily', 'repayment_frequency' => 'fortnightly', 'status' => 'Active'])->assertCreated();
        $this->assertDatabaseHas('loan_product_terms', ['loan_product_id' => $product->id, 'duration' => 14, 'interest_cycle' => 'daily', 'repayment_frequency' => 'fortnightly']);
    }

    public function test_legacy_forms_cannot_bypass_lender_ownership_and_customer_access_controls(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $payload = ['name' => 'Untrusted affiliate', 'address' => 'Test', 'phone' => '256700000000', 'email' => 'legacy@example.org', 'lender_relationship' => 'affiliated', 'authority_basis' => 'exempt', 'authority_reference' => 'FAKE'];
        $this->actingAs($customer)->post('/institutions', $payload)->assertForbidden();
        $this->post('/loan-products', ['name' => 'Untrusted product', 'type' => 'Cash'])->assertForbidden();
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $this->actingAs($operations)->post('/institutions', $payload)->assertRedirect();
        $lender = Institution::where('email', 'legacy@example.org')->firstOrFail();
        $this->assertSame('independent', $lender->lender_relationship);
        $this->assertSame('pending', $lender->authority_basis);
        $this->assertNull($lender->authority_reference);
    }

    public function test_upgrade_lender_with_pending_or_missing_authority_cannot_originate(): void
    {
        [$lender, $product, $term] = $this->route();
        $lender->update(['authority_basis' => 'pending', 'authority_reference' => null]);
        $this->assertSame('LENDER_AUTHORITY_INCOMPLETE', app(CreditDistributionService::class)->assess($product->fresh(), $term, 'web')['code']);
        $this->assertCount(0, app(PlatformCreditRoutingService::class)->options('web', 'UG', 10000, 'Emergency'));
        $this->expectException(InvalidArgumentException::class);
        app(PlatformCreditRoutingService::class)->assertOrigination(new LoanApplication(['loan_product_id' => $product->id, 'loan_product_term_id' => $term->id, 'institution_id' => $lender->id, 'amount' => 10000, 'reason' => 'Emergency', 'distribution_channel' => 'web']));
    }

    public function test_admin_creates_category_without_violating_legacy_product_type(): void
    {
        [$lender] = $this->route();
        $this->admin();
        $this->postJson('/api/admin/lending-platform/products', ['institution_id' => $lender->id, 'name' => 'Personal credit', 'product_category' => 'personal_loan', 'country' => 'UG', 'currency' => 'UGX', 'status' => 'Active', 'min_amount_minor' => 1])
            ->assertCreated()->assertJsonPath('data.product.type', 'Cash')->assertJsonPath('data.product.product_category', 'personal_loan');
        $this->assertDatabaseHas('loan_products', ['name' => 'Personal credit', 'type' => 'Cash']);
    }

    public function test_country_metadata_does_not_activate_an_uncertified_currency_route(): void
    {
        [, $product, $term] = $this->route();
        $product->update(['country' => 'KE', 'currency' => 'KES']);
        $policy = app(CreditDistributionService::class);
        $this->assertSame('COUNTRY_NOT_ACTIVATED', $policy->assess($product, $term, 'web')['code']);
        config(['credit_distribution.enabled_countries' => ['UG', 'KE']]);
        $this->assertSame('CURRENCY_ROUTE_NOT_ACTIVATED', $policy->assess($product, $term, 'web')['code']);
    }
}
