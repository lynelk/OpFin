<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerFinancialIntentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_partner_referral_replay_returns_the_existing_request(): void
    {
        [$partner, $partnerAccountId] = $this->partner();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);

        $payload = $this->payload($partnerAccountId, 'stolets-ref-001');

        $first = $this->postJson(
            '/api/partner/financial-intents/'.$customer->id,
            $payload,
            ['Idempotency-Key' => 'partner-referral-001'],
        )->assertCreated();

        $second = $this->postJson(
            '/api/partner/financial-intents/'.$customer->id,
            $payload,
            ['Idempotency-Key' => 'partner-referral-001'],
        )->assertCreated();

        $this->assertSame(
            $first->json('data.request.reference'),
            $second->json('data.request.reference'),
        );
        $this->assertDatabaseCount('partner_financial_intent_requests', 1);
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_customer_or_payload(): void
    {
        [$partner, $partnerAccountId] = $this->partner();
        $firstCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $secondCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);

        $this->postJson(
            '/api/partner/financial-intents/'.$firstCustomer->id,
            $this->payload($partnerAccountId, 'stolets-ref-002'),
            ['Idempotency-Key' => 'partner-referral-collision'],
        )->assertCreated();

        $this->postJson(
            '/api/partner/financial-intents/'.$secondCustomer->id,
            $this->payload($partnerAccountId, 'stolets-ref-003'),
            ['Idempotency-Key' => 'partner-referral-collision'],
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'The Idempotency-Key or external reference is already bound to a different financial-intent request.',
            );

        $this->assertDatabaseCount('partner_financial_intent_requests', 1);
    }

    public function test_external_reference_cannot_be_reused_with_changed_financial_terms(): void
    {
        [$partner, $partnerAccountId] = $this->partner();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);

        $this->postJson(
            '/api/partner/financial-intents/'.$customer->id,
            $this->payload($partnerAccountId, 'stolets-ref-004'),
            ['Idempotency-Key' => 'partner-referral-004-a'],
        )->assertCreated();

        $changed = $this->payload($partnerAccountId, 'stolets-ref-004');
        $changed['amount_minor'] = 275000;

        $this->postJson(
            '/api/partner/financial-intents/'.$customer->id,
            $changed,
            ['Idempotency-Key' => 'partner-referral-004-b'],
        )->assertStatus(409);

        $this->assertDatabaseCount('partner_financial_intent_requests', 1);
        $this->assertDatabaseHas('partner_financial_intent_requests', [
            'customer_user_id' => $customer->id,
            'external_reference' => 'stolets-ref-004',
            'amount_minor' => 250000,
        ]);
    }

    public function test_expired_partner_referral_is_persisted_as_expired_when_customer_confirms(): void
    {
        [$partner, $partnerAccountId] = $this->partner();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($partner);

        $this->postJson(
            '/api/partner/financial-intents/'.$customer->id,
            $this->payload($partnerAccountId, 'stolets-ref-005'),
            ['Idempotency-Key' => 'partner-referral-005'],
        )->assertCreated();

        $requestId = DB::table('partner_financial_intent_requests')
            ->where('external_reference', 'stolets-ref-005')
            ->value('id');

        DB::table('partner_financial_intent_requests')
            ->where('id', $requestId)
            ->update(['expires_at' => now()->subMinute()]);

        $spaceId = $this->personalSpace($customer);
        Sanctum::actingAs($customer);

        $this->postJson('/api/partner-financial-intents/'.$requestId.'/confirm', [
            'financial_space_id' => $spaceId,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This partner financial intent request has expired.');

        $this->assertDatabaseHas('partner_financial_intent_requests', [
            'id' => $requestId,
            'status' => 'expired',
            'confirmed_financial_intent_id' => null,
        ]);
    }

    private function partner(): array
    {
        $partner = User::factory()->create(['role' => User::ROLE_PARTNER_API]);

        $partnerAccountId = DB::table('partner_distribution_accounts')->insertGetId([
            'reference' => (string) Str::uuid(),
            'created_by' => $partner->id,
            'partner_name' => 'Stolets Test Partner',
            'partner_type' => 'platform',
            'status' => 'active',
            'allowed_products' => json_encode(['financial_intents'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$partner, $partnerAccountId];
    }

    private function payload(int $partnerAccountId, string $externalReference): array
    {
        return [
            'partner_account_id' => $partnerAccountId,
            'source_platform' => 'stolets',
            'external_reference' => $externalReference,
            'need_type' => 'working_capital',
            'amount_minor' => 250000,
            'currency' => 'UGX',
            'purpose' => ['reason' => 'stock replenishment'],
            'customer_consent_reference' => 'STOLETS-CONSENT-001',
            'metadata' => ['merchant_context' => 'retail'],
        ];
    }

    private function personalSpace(User $user): int
    {
        $spaceId = DB::table('financial_spaces')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'type' => 'personal',
            'name' => 'Personal',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $spaceId,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $spaceId;
    }
}
