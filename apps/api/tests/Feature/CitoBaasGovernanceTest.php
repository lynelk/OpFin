<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Cito\CitoBillingClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitoBaasGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $key): array
    {
        return [
            'billingAccountReference' => 'ACC-001',
            'serviceCode' => 'MESSAGING',
            'usageQuantity' => '1',
            'netAmount' => '100',
            'currency' => 'UGX',
            'idempotencyKey' => $key,
        ];
    }

    public function test_only_platform_billing_authority_can_draft(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CUSTOMER]));
        $this->postJson('/api/admin/cito-baas/intents', [
            'operation' => 'charge', 'idempotency_key' => 'billing-draft-1',
            'payload' => $this->payload('billing-draft-1'),
        ])->assertForbidden();
        $this->assertDatabaseCount('cito_baas_operation_intents', 0);
    }

    public function test_draft_is_durable_and_rejects_changed_instruction(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        $request = [
            'operation' => 'charge', 'idempotency_key' => 'billing-draft-2',
            'payload' => $this->payload('billing-draft-2'),
        ];
        $first = $this->postJson('/api/admin/cito-baas/intents', $request)->assertCreated();
        $this->assertNull($first->json('data.intent.request_payload'));
        $this->postJson('/api/admin/cito-baas/intents', $request)->assertCreated();
        $this->postJson('/api/admin/cito-baas/intents', [
            ...$request, 'payload' => [...$request['payload'], 'netAmount' => '999'],
        ])->assertStatus(409);
        $this->assertDatabaseCount('cito_baas_operation_intents', 1);
    }

    public function test_unrecognised_fields_cannot_leave_opfin_via_a_billing_intent(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        $this->postJson('/api/admin/cito-baas/intents', [
            'operation' => 'charge', 'idempotency_key' => 'no-secret-export',
            'payload' => [...$this->payload('no-secret-export'), 'privateKey' => 'must-not-leave'],
        ])->assertStatus(409)->assertJsonFragment([
            'message' => 'Unexpected field in Cito BaaS operation.',
        ]);
        $this->assertDatabaseCount('cito_baas_operation_intents', 0);
    }

    public function test_maker_cannot_approve_and_unaccepted_billing_cannot_submit(): void
    {
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($maker);
        $id = $this->postJson('/api/admin/cito-baas/intents', [
            'operation' => 'charge', 'idempotency_key' => 'billing-draft-3',
            'payload' => $this->payload('billing-draft-3'),
        ])->assertCreated()->json('data.intent.id');
        $this->postJson("/api/admin/cito-baas/intents/{$id}/approve")->assertStatus(409);
        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/cito-baas/intents/{$id}/approve")->assertStatus(503);
        $this->assertDatabaseHas('cito_baas_operation_intents', [
            'id' => $id, 'status' => 'pending_approval',
        ]);
    }

    public function test_production_account_scope_rejects_unapproved_billing_targets(): void
    {
        config([
            'services.cito.environment' => 'PRODUCTION',
            'services.cito.baas_allowed_accounts' => ['ACC-APPROVED'],
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]));
        $this->postJson('/api/admin/cito-baas/intents', [
            'operation' => 'charge', 'idempotency_key' => 'prod-account-denied',
            'payload' => $this->payload('prod-account-denied'),
        ])->assertStatus(409);
    }

    public function test_approved_charge_is_sent_once_and_never_assumed_earned_revenue(): void
    {
        config([
            'services.cito.environment' => 'SANDBOX',
            'services.cito.feature_flags.billing' => true,
            'services.cito.baas_write_enabled' => true,
            'services.cito.baas_daily_write_limit' => 1,
        ]);
        $client = $this->mock(CitoBillingClient::class);
        $client->shouldReceive('authorizeCharge')->once()->andReturn([
            'reference' => 'cito-charge-123', 'status' => 'ACCEPTED',
        ]);
        $maker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        $checker = User::factory()->create(['role' => User::ROLE_PLATFORM_ADMIN]);
        Sanctum::actingAs($maker);
        $id = $this->postJson('/api/admin/cito-baas/intents', [
            'operation' => 'charge', 'idempotency_key' => 'billing-charge-4',
            'payload' => $this->payload('billing-charge-4'),
        ])->assertCreated()->json('data.intent.id');
        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/cito-baas/intents/{$id}/approve")
            ->assertOk()->assertJsonPath('data.intent.status', 'submitted_unconfirmed');
        $this->postJson("/api/admin/cito-baas/intents/{$id}/approve")
            ->assertOk()->assertJsonPath('data.intent.status', 'submitted_unconfirmed');
        $this->assertDatabaseHas('cito_baas_operation_intents', [
            'id' => $id, 'provider_reference' => 'cito-charge-123',
        ]);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseHas('cito_baas_daily_write_limits', [
            'environment' => 'SANDBOX', 'reserved_calls' => 1,
        ]);
    }
}
