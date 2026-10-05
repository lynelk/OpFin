<?php

namespace Tests\Feature;

use App\Models\FinancialIntent;
use App\Models\FinancialProduct;
use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\ShariaApproval;
use App\Models\User;
use App\Services\FinancingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFactoryTest extends TestCase
{
    use RefreshDatabase;

    private User $adminA;

    private User $adminB;

    private User $operations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminA = User::factory()->create(['role' => 'platform_admin']);
        $this->adminB = User::factory()->create(['role' => 'platform_admin']);
        $this->operations = User::factory()->create(['role' => 'operations']);
    }

    public function test_templates_need_a_second_administrator_and_rail_consistent_contracts(): void
    {
        $this->as($this->operations)->postJson('/api/admin/financing-factory/templates', $this->templateBody(), $this->key('t0'))->assertForbidden();
        $id = $this->as($this->adminA)->postJson('/api/admin/financing-factory/templates', $this->templateBody(), $this->key('t1'))
            ->assertCreated()->assertJsonPath('data.template.status', 'draft')->json('data.template.id');
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/templates/{$id}/approve")->assertForbidden();
        $this->as($this->adminB)->postJson("/api/admin/financing-factory/templates/{$id}/approve")->assertOk()->assertJsonPath('data.template.status', 'active');

        $this->as($this->adminA)->postJson('/api/admin/financing-factory/templates', $this->templateBody(['code' => 'ISLAMIC_BAD', 'rail' => 'ISLAMIC']), $this->key('t2'))
            ->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Contract types must come from the ISLAMIC rail'));
        $body = $this->templateBody(['code' => 'NO_ASSETS']);
        $body['guardrails']['asset_classes'] = [];
        $this->as($this->adminA)->postJson('/api/admin/financing-factory/templates', $body, $this->key('t3'))
            ->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'must name the asset classes'));
    }

    public function test_products_stay_within_template_guardrails_and_name_their_parties(): void
    {
        $template = $this->activeTemplate();
        $passport = $this->approvedPassport();
        $cases = [
            ['parameters' => ['price_bps' => 4600], 'expect' => 'APR must be at most 4500 basis points'],
            ['parameters' => ['asset_classes' => ['car']], 'expect' => 'An asset class is not allowed by the template'],
            ['parameters' => ['tenor_months' => ['min' => 1, 'max' => 12]], 'expect' => 'tenor must sit within 3 to 18 months'],
            ['parameters' => ['fees' => [['type' => 'arrangement', 'bps' => 400]]], 'expect' => 'arrangement fee is above'],
            ['disclosure' => ['complaints_contact' => ''], 'expect' => 'Add the required disclosure: complaints contact'],
        ];
        foreach ($cases as $index => $case) {
            $expect = $case['expect'];
            unset($case['expect']);
            $this->as($this->operations)->postJson('/api/admin/financing-factory/products', $this->productBody($template, $passport, $case), $this->key('bad-'.$index))
                ->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, $expect));
        }
        foreach (['lender_reference', 'funder_reference', 'principal_reference'] as $party) {
            $this->as($this->operations)->postJson('/api/admin/financing-factory/products', $this->productBody($template, $passport, [$party => ' ']), $this->key('no-'.$party))
                ->assertUnprocessable()->assertJsonValidationErrors($party);
        }
        $this->assertDatabaseCount('financial_products', 0);
        $this->as($this->operations)->postJson('/api/admin/financing-factory/products', $this->productBody($template, $passport), $this->key('good'))
            ->assertCreated()->assertJsonPath('data.product.status', 'draft')->assertJsonPath('data.product.lender_reference', 'Synthetic Lender Ltd (test)');
    }

    public function test_product_lifecycle_needs_separate_people_and_an_approved_passport(): void
    {
        $template = $this->activeTemplate();
        $passport = $this->draftPassport();
        $id = $this->as($this->adminA)->postJson('/api/admin/financing-factory/products', $this->productBody($template, $passport), $this->key('p1'))->assertCreated()->json('data.product.id');
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/submit")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/approve")->assertForbidden();
        $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$id}/approve")->assertForbidden();
        $this->as($this->adminB)->postJson("/api/admin/financing-factory/products/{$id}/approve")->assertOk()->assertJsonPath('data.product.status', 'approved');

        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/activate")
            ->assertUnprocessable()->assertJsonPath('message', 'Activation needs an approved, current Legal Product Passport.');
        $this->as($this->adminB)->postJson("/api/admin/financing-factory/passports/{$passport}/approve",
            ['licence_or_approval_reference' => 'SYN-LICENCE-1', 'evidence_reference' => 'SYN-EVIDENCE-1'])->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/activate")->assertOk()->assertJsonPath('data.product.status', 'live');

        $this->assertSame([$id], $this->matchingProductIds());
        $this->as($this->adminA)->putJson("/api/admin/financing-factory/products/{$id}", ['name' => 'Changed after approval'])->assertStatus(409);
    }

    public function test_a_revision_is_a_new_version_and_activation_retires_the_previous_one(): void
    {
        $template = $this->activeTemplate();
        $v1 = $this->liveProduct($template, $this->approvedPassport());
        $v2 = $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$v1}/revise",
            ['parameters' => [...$this->parameters(), 'price_bps' => 3900]], $this->key('rev'))
            ->assertCreated()->assertJsonPath('data.product.version', 2)->assertJsonPath('data.product.supersedes_product_id', $v1)->json('data.product.id');
        $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$v2}/submit")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$v2}/approve")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$v2}/activate")->assertOk();

        $this->assertSame('retired', FinancialProduct::query()->find($v1)->status);
        $this->assertSame(4200, FinancialProduct::query()->find($v1)->parameters['price_bps'], 'The approved version is unchanged.');
        $this->assertSame([$v2], $this->matchingProductIds());
    }

    public function test_islamic_products_need_a_governance_sharia_approval_and_conventional_ones_refuse_it(): void
    {
        $template = $this->activeTemplate($this->templateBody(['code' => 'DEVICE_MURABAHA', 'name' => 'Device murabaha', 'rail' => 'ISLAMIC',
            'contract_types' => ['MURABAHA']]));
        $passport = $this->approvedPassport();
        $body = $this->productBody($template, $passport, ['code' => 'SYN_PHONE_MURABAHA', 'contract_type' => 'MURABAHA']);
        $id = $this->as($this->operations)->postJson('/api/admin/financing-factory/products', $body, $this->key('i1'))->assertCreated()->json('data.product.id');
        $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$id}/submit")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/approve")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/activate")
            ->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'cannot create one'));

        $approval = ShariaApproval::query()->create(['reference' => (string) Str::uuid(), 'authority_name' => 'Synthetic Sharia board (test)',
            'approval_reference' => 'SYN-SSB-1', 'scope_type' => 'product', 'scope_reference' => 'SYN_PHONE_MURABAHA', 'status' => 'approved',
            'effective_from' => now()->subDay()]);
        $v2 = $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$id}/revise", ['sharia_approval_id' => $approval->id], $this->key('i2'))
            ->assertCreated()->json('data.product.id');
        $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$v2}/submit")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$v2}/approve")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$v2}/activate")->assertOk()->assertJsonPath('data.product.rail', 'ISLAMIC');

        $conventional = $this->activeTemplate($this->templateBody(['code' => 'DEVICE_HP_2']));
        $this->as($this->operations)->postJson('/api/admin/financing-factory/products',
            $this->productBody($conventional, $passport, ['code' => 'SYN_CONV_WITH_SHARIA', 'sharia_approval_id' => $approval->id]), $this->key('i3'))
            ->assertUnprocessable()->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'cannot carry a Sharia approval'));
    }

    public function test_revoking_a_passport_takes_live_products_out_of_matching_at_once(): void
    {
        $passport = $this->approvedPassport();
        $this->liveProduct($this->activeTemplate(), $passport);
        $this->assertCount(1, $this->matchingProductIds());

        $this->as($this->adminA)->postJson("/api/admin/financing-factory/passports/{$passport}/revoke", ['reason' => 'Licence lapsed (test)'])->assertOk();
        $this->assertSame([], $this->matchingProductIds());
    }

    private function liveProduct(int $template, int $passport): int
    {
        $id = $this->as($this->operations)->postJson('/api/admin/financing-factory/products', $this->productBody($template, $passport), $this->key('live-'.$template))
            ->assertCreated()->json('data.product.id');
        $this->as($this->operations)->postJson("/api/admin/financing-factory/products/{$id}/submit")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/approve")->assertOk();
        $this->as($this->adminA)->postJson("/api/admin/financing-factory/products/{$id}/activate")->assertOk();

        return $id;
    }

    private function activeTemplate(?array $body = null): int
    {
        $body ??= $this->templateBody();
        $id = $this->as($this->adminA)->postJson('/api/admin/financing-factory/templates', $body, $this->key('tpl-'.$body['code']))->assertCreated()->json('data.template.id');
        $this->as($this->adminB)->postJson("/api/admin/financing-factory/templates/{$id}/approve")->assertOk();

        return $id;
    }

    private function draftPassport(): int
    {
        return $this->as($this->operations)->postJson('/api/admin/financing-factory/passports', ['jurisdiction' => 'UG', 'regulated_activity' => 'asset_finance',
            'booking_entity' => 'Synthetic Lender Ltd (test)'], $this->key('pp-'.Str::random(6)))->assertCreated()->json('data.passport.id');
    }

    private function approvedPassport(): int
    {
        $id = $this->draftPassport();
        $this->as($this->adminB)->postJson("/api/admin/financing-factory/passports/{$id}/approve",
            ['licence_or_approval_reference' => 'SYN-LICENCE-1', 'evidence_reference' => 'SYN-EVIDENCE-1'])->assertOk();

        return $id;
    }

    private function matchingProductIds(): array
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $space = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => 'personal', 'name' => 'Synthetic customer space',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $customer->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $intent = FinancialIntent::query()->create(['reference' => (string) Str::uuid(), 'user_id' => $customer->id, 'financial_space_id' => $space->id,
            'need_type' => 'device', 'principles_preference' => 'ALL_SUITABLE', 'currency' => 'UGX', 'status' => 'open']);

        return app(FinancingService::class)->matchingProducts($intent)->pluck('id')->all();
    }

    private function templateBody(array $overrides = []): array
    {
        return array_replace([
            'code' => 'DEVICE_HP', 'name' => 'Device hire purchase', 'family' => 'device_finance', 'rail' => 'CONVENTIONAL',
            'contract_types' => ['HIRE_PURCHASE'], 'policy_reference' => 'Synthetic credit policy v1, section 4 (test fixture)',
            'guardrails' => ['tenor_months' => ['min' => 3, 'max' => 18], 'price_max_bps' => 4500, 'deposit_min_bps' => 1500,
                'ltv_max_bps' => 8500, 'fee_types' => ['arrangement', 'late_payment'], 'fee_max_bps' => 300,
                'asset_classes' => ['phone', 'tablet', 'laptop'], 'required_disclosures' => ['total_cost', 'late_payment_consequences', 'complaints_contact']],
        ], $overrides);
    }

    private function parameters(): array
    {
        return ['tenor_months' => ['min' => 6, 'max' => 12], 'price_bps' => 4200, 'deposit_bps' => 2000, 'ltv_bps' => 8000,
            'fees' => [['type' => 'arrangement', 'bps' => 200]], 'asset_classes' => ['phone']];
    }

    private function productBody(int $template, int $passport, array $overrides = []): array
    {
        $body = ['code' => 'SYN_PHONE_HP', 'product_template_id' => $template, 'name' => 'Synthetic phone plan', 'contract_type' => 'HIRE_PURCHASE',
            'legal_product_passport_id' => $passport, 'parameters' => $this->parameters(),
            'disclosure' => ['total_cost' => 'Synthetic total cost wording.', 'late_payment_consequences' => 'Synthetic late payment wording.',
                'complaints_contact' => 'Synthetic complaints contact.'],
            'lender_reference' => 'Synthetic Lender Ltd (test)', 'funder_reference' => 'Synthetic Lender balance sheet (test)',
            'principal_reference' => 'Synthetic Lender Ltd (test)'];
        foreach ($overrides as $key => $value) {
            $body[$key] = in_array($key, ['parameters', 'disclosure'], true) ? [...$body[$key], ...$value] : $value;
        }

        return $body;
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    private function key(string $key): array
    {
        return ['Idempotency-Key' => $key];
    }
}
