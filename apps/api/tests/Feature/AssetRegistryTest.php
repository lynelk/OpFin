<?php

namespace Tests\Feature;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $operations;

    private User $admin;

    private FinancialSpace $space;

    protected function setUp(): void
    {
        parent::setUp();
        config(['asset_registry.identifier_key' => str_repeat('synthetic-registry-key-', 2)]);
        $this->owner = User::factory()->create(['role' => 'customer']);
        $this->operations = User::factory()->create(['role' => 'operations']);
        $this->admin = User::factory()->create(['role' => 'platform_admin']);
        $this->space = $this->space($this->owner);
    }

    public function test_phone_registers_with_a_valid_imei_and_never_returns_the_raw_identifier(): void
    {
        $imei = $this->imei('35693803564380');
        $response = $this->register($this->space, $this->owner, $this->phone($imei))->assertCreated()
            ->assertJsonPath('data.asset.status', 'registered')
            ->assertJsonPath('data.asset.family', 'device')
            ->assertJsonPath('data.asset.identifiers.0.masked', '********'.substr($imei, -4))
            ->assertJsonPath('data.asset.remote_device_controls', 'not_available');

        $this->assertStringNotContainsString($imei, $response->getContent());
        $this->assertDatabaseMissing('asset_identifiers', ['value_hmac' => $imei]);
    }

    public function test_identifier_rules_are_class_specific_and_imeis_are_check_digit_validated(): void
    {
        $valid = $this->imei('35693803564380');
        $this->register($this->space, $this->owner, $this->phone(substr($valid, 0, 14).(((int) substr($valid, -1) + 1) % 10)))
            ->assertUnprocessable()->assertJsonPath('message', 'This IMEI fails its check digit. Compare it with *#06# or the box label.');
        $this->register($this->space, $this->owner, ['asset_class' => 'laptop', 'identifiers' => [['type' => 'imei', 'value' => $this->imei('35693803564380')]]], 'laptop')
            ->assertUnprocessable()->assertJsonPath('message', 'This asset needs one of these identifiers: serial.');
        $this->register($this->space, $this->owner, ['asset_class' => 'motorcycle', 'identifiers' => [['type' => 'vin', 'value' => 'SYNTHVEHCLE00000I']]], 'bike')
            ->assertUnprocessable();
        $this->register($this->space, $this->owner, ['asset_class' => 'motorcycle', 'make' => 'Synthetic', 'identifiers' => [
            ['type' => 'vin', 'value' => 'synthvehcle000001'], ['type' => 'registration_plate', 'value' => 'UEA 123B'],
        ]], 'bike-ok')->assertCreated()->assertJsonPath('data.asset.family', 'vehicle');
        $this->register($this->space, $this->owner, ['asset_class' => 'solar_system', 'identifiers' => [['type' => 'serial', 'value' => 'SYN-SOLAR-0001']]], 'solar')
            ->assertCreated()->assertJsonPath('data.asset.family', 'productive');
    }

    public function test_registration_replays_its_idempotency_key_and_refuses_reuse_for_another_asset(): void
    {
        $first = $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertCreated()->json('data.asset.id');
        $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertCreated()->assertJsonPath('data.asset.id', $first);
        $this->register($this->space, $this->owner, $this->phone($this->imei('49015420323751')))->assertStatus(409);
        $this->assertSame(1, DB::table('asset_passports')->count());
    }

    public function test_an_identifier_already_active_elsewhere_goes_to_review_without_revealing_the_other_space(): void
    {
        $imei = $this->imei('35693803564380');
        $this->register($this->space, $this->owner, $this->phone($imei))->assertCreated();
        $other = User::factory()->create(['role' => 'customer']);
        $response = $this->register($this->space($other), $other, $this->phone($imei), 'other-space')->assertCreated()
            ->assertJsonPath('data.asset.status', 'review_required')
            ->assertJsonPath('data.asset.identifiers.0.active', false);
        $this->assertArrayNotHasKey('review_reason', $response->json('data.asset'));
        $this->assertArrayNotHasKey('financial_space_id', $response->json('data.asset'));

        Sanctum::actingAs($this->operations);
        $this->getJson('/api/admin/asset-passports/review-queue')->assertOk()
            ->assertJsonPath('data.assets.0.review_reason', 'identifier_active_on_another_passport');
    }

    public function test_the_registrant_cannot_verify_their_own_asset(): void
    {
        FinancialSpaceMembership::query()->create(['financial_space_id' => $this->space->id, 'user_id' => $this->operations->id, 'role' => 'administrator', 'status' => 'active', 'joined_at' => now()]);
        $asset = $this->register($this->space, $this->operations, $this->phone($this->imei('35693803564380')))->assertCreated()->json('data.asset.id');

        $this->verify($asset, $this->operations)->assertForbidden();
        $this->verify($asset, $this->admin, 'second')->assertOk()->assertJsonPath('data.asset.status', 'verified');
    }

    public function test_one_active_lien_early_release_needs_an_administrator_and_stolen_assets_cannot_be_financed(): void
    {
        $asset = $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertCreated()->json('data.asset.id');
        $this->verify($asset, $this->operations)->assertOk();
        $first = $this->arrangement();
        $this->encumber($asset, $first, 'lien-1')->assertCreated()->assertJsonPath('data.asset.status', 'encumbered')->assertJsonPath('data.asset.secured_by_financing', true);
        $this->encumber($asset, $this->arrangement(), 'lien-2')->assertStatus(409);
        $lien = (int) DB::table('asset_encumbrances')->value('id');

        $this->release($lien, $this->operations, 'early')->assertForbidden();
        DB::table('financing_arrangements')->where('id', $first)->update(['settled_at' => now(), 'status' => 'settled']);
        $this->release($lien, $this->operations, 'settled')->assertOk()->assertJsonPath('data.asset.status', 'verified')->assertJsonPath('data.asset.secured_by_financing', false);

        Sanctum::actingAs($this->owner);
        $this->postJson('/api/financial-spaces/'.$this->space->id.'/asset-passports/'.$asset.'/report-stolen', ['reason' => 'Taken from the shop', 'police_reference' => 'SYN/CRB/001/2026'],
            ['Idempotency-Key' => 'stolen'])->assertOk()->assertJsonPath('data.asset.status', 'reported_stolen');
        $this->encumber($asset, $this->arrangement(), 'lien-3')->assertStatus(409);
        $check = $this->checkIdentifier($this->imei('35693803564380'))->assertOk()
            ->assertJsonPath('data.reported_stolen', true)->assertJsonPath('data.registered', true)->json('data');
        $this->assertArrayNotHasKey('financial_space_id', $check);
        $this->assertArrayNotHasKey('owner_user_id', $check);

        Sanctum::actingAs($this->operations);
        $this->postJson('/api/admin/asset-passports/'.$asset.'/recover', ['reason' => 'Recovered by police'], ['Idempotency-Key' => 'recover'])
            ->assertOk()->assertJsonPath('data.asset.status', 'verified');
    }

    public function test_disposal_releases_the_identifier_for_a_resale_registration(): void
    {
        $imei = $this->imei('35693803564380');
        $asset = $this->register($this->space, $this->owner, $this->phone($imei))->assertCreated()->json('data.asset.id');
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/financial-spaces/'.$this->space->id.'/asset-passports/'.$asset.'/dispose', ['disposal' => 'sold', 'reason' => 'Sold to a new owner'],
            ['Idempotency-Key' => 'dispose'])->assertOk()->assertJsonPath('data.asset.status', 'disposed');

        $buyer = User::factory()->create(['role' => 'customer']);
        $this->register($this->space($buyer), $buyer, $this->phone($imei), 'resale')->assertCreated()->assertJsonPath('data.asset.status', 'registered');
    }

    public function test_review_clears_only_after_the_conflicting_passport_is_resolved(): void
    {
        $imei = $this->imei('35693803564380');
        $original = $this->register($this->space, $this->owner, $this->phone($imei))->assertCreated()->json('data.asset.id');
        $buyer = User::factory()->create(['role' => 'customer']);
        $buyerSpace = $this->space($buyer);
        $pending = $this->register($buyerSpace, $buyer, $this->phone($imei), 'buyer')->assertCreated()->json('data.asset.id');

        Sanctum::actingAs($this->operations);
        $this->postJson('/api/admin/asset-passports/'.$pending.'/review', ['decision' => 'activate', 'reason' => 'Buyer receipt seen'], ['Idempotency-Key' => 'clear-1'])->assertStatus(409);
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/financial-spaces/'.$this->space->id.'/asset-passports/'.$original.'/dispose', ['disposal' => 'sold', 'reason' => 'Sold'], ['Idempotency-Key' => 'sold'])->assertOk();
        Sanctum::actingAs($this->operations);
        $this->postJson('/api/admin/asset-passports/'.$pending.'/review', ['decision' => 'activate', 'reason' => 'Seller confirmed the sale'], ['Idempotency-Key' => 'clear-2'])
            ->assertOk()->assertJsonPath('data.asset.status', 'registered')->assertJsonPath('data.asset.identifiers.0.active', true);
    }

    public function test_assets_are_isolated_to_their_space(): void
    {
        $asset = $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertCreated()->json('data.asset.id');
        $stranger = User::factory()->create(['role' => 'customer']);
        $strangerSpace = $this->space($stranger);

        Sanctum::actingAs($stranger);
        $this->getJson('/api/financial-spaces/'.$this->space->id.'/asset-passports')->assertForbidden();
        $this->getJson('/api/financial-spaces/'.$strangerSpace->id.'/asset-passports/'.$asset)->assertNotFound();
        $this->postJson('/api/admin/asset-passports/identifier-check', ['type' => 'imei', 'value' => $this->imei('35693803564380')])->assertForbidden();
    }

    public function test_passport_history_is_append_only(): void
    {
        $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertCreated();
        $this->expectException(QueryException::class);
        DB::table('asset_passport_events')->update(['to_status' => 'verified']);
    }

    public function test_registry_is_unavailable_until_its_identifier_key_is_configured(): void
    {
        config(['asset_registry.identifier_key' => null]);
        $this->register($this->space, $this->owner, $this->phone($this->imei('35693803564380')))->assertStatus(503);
    }

    private function register(FinancialSpace $space, User $actor, array $body, string $key = 'register')
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/financial-spaces/'.$space->id.'/asset-passports', $body, ['Idempotency-Key' => $key]);
    }

    private function verify(int $asset, User $actor, string $key = 'verify')
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/admin/asset-passports/'.$asset.'/verify', ['method' => 'physical_inspection', 'evidence_reference' => 'SYN-INSPECTION-1'], ['Idempotency-Key' => $key]);
    }

    private function encumber(int $asset, int $arrangement, string $key)
    {
        Sanctum::actingAs($this->operations);

        return $this->postJson('/api/admin/asset-passports/'.$asset.'/encumbrances', ['financing_arrangement_id' => $arrangement], ['Idempotency-Key' => $key]);
    }

    private function release(int $lien, User $actor, string $key)
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/admin/asset-encumbrances/'.$lien.'/release', ['reason' => 'Arrangement settled'], ['Idempotency-Key' => $key]);
    }

    private function checkIdentifier(string $imei)
    {
        Sanctum::actingAs($this->operations);

        return $this->postJson('/api/admin/asset-passports/identifier-check', ['type' => 'imei', 'value' => $imei]);
    }

    private function phone(string $imei): array
    {
        return ['asset_class' => 'phone', 'make' => 'Synthetic', 'model' => 'Test Phone', 'identifiers' => [['type' => 'imei', 'value' => $imei]]];
    }

    /** Appends the Luhn check digit to a 14-digit synthetic prefix. */
    private function imei(string $prefix): string
    {
        $sum = 0;
        foreach (str_split($prefix) as $index => $digit) {
            $value = (int) $digit * ($index % 2 === 1 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $prefix.((10 - $sum % 10) % 10);
    }

    private function space(User $owner): FinancialSpace
    {
        $space = FinancialSpace::query()->create(['public_id' => (string) Str::uuid(), 'type' => 'personal', 'name' => 'Synthetic asset space',
            'country' => 'UG', 'currency' => 'UGX', 'status' => 'active']);
        FinancialSpaceMembership::query()->create(['financial_space_id' => $space->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now()]);

        return $space;
    }

    private function arrangement(): int
    {
        $product = DB::table('financial_products')->insertGetId(['reference' => (string) Str::uuid(), 'code' => 'SYN-ASSET-'.Str::random(8), 'version' => 1,
            'name' => 'Synthetic device finance', 'rail' => 'CONVENTIONAL', 'family' => 'asset_finance', 'contract_type' => 'hire_purchase',
            'jurisdiction' => 'UG', 'currency' => 'UGX', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $intent = DB::table('financial_intents')->insertGetId(['reference' => (string) Str::uuid(), 'user_id' => $this->owner->id,
            'financial_space_id' => $this->space->id, 'need_type' => 'asset', 'created_at' => now(), 'updated_at' => now()]);
        $application = DB::table('financing_applications')->insertGetId(['reference' => (string) Str::uuid(), 'financial_intent_id' => $intent,
            'financial_product_id' => $product, 'user_id' => $this->owner->id, 'financial_space_id' => $this->space->id, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now()]);

        return DB::table('financing_arrangements')->insertGetId(['reference' => (string) Str::uuid(), 'financing_application_id' => $application,
            'financial_product_id' => $product, 'user_id' => $this->owner->id, 'financial_space_id' => $this->space->id, 'contract_type' => 'hire_purchase',
            'principal_or_cost_minor' => 1500000, 'total_obligation_minor' => 1800000, 'currency' => 'UGX', 'status' => 'active',
            'contract_snapshot' => json_encode(['synthetic' => true]), 'contract_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
    }
}
