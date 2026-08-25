<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\MobileMoneyTransaction;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\ProtectionProduct;
use App\Models\SavingsMovement;
use App\Models\SavingsProduct;
use App\Models\User;
use App\Services\ProtectionService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveProtectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_product_requires_independent_approval_and_partner_confirmation_before_balance(): void
    {
        [$customer, $maker, $checker] = $this->actors();

        Sanctum::actingAs($maker);
        $created = $this->postJson('/api/admin/savings-products', $this->savingsProductPayload())
            ->assertCreated()
            ->assertJsonPath('data.product.status', SavingsProduct::STATUS_DRAFT);
        $productId = (int) $created->json('data.product.id');

        $this->getJson('/api/savings/products')
            ->assertOk()
            ->assertJsonCount(0, 'data.products');

        $this->postJson("/api/admin/savings-products/{$productId}/activate", $this->approvalPayload('SAVE-MAKER'))
            ->assertStatus(409);

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/savings-products/{$productId}/activate", $this->approvalPayload('SAVE-CHECKER'))
            ->assertOk()
            ->assertJsonPath('data.product.status', SavingsProduct::STATUS_ACTIVE)
            ->assertJsonPath('data.product.approved_by', $checker->id);

        Sanctum::actingAs($customer);
        $this->getJson('/api/savings/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.custody_model', 'partner_held');

        $goalResponse = $this->postJson('/api/savings/goals', [
            'savings_product_id' => $productId,
            'name' => 'Emergency fund',
            'target_amount_minor' => 100000,
            'target_date' => now()->addMonths(4)->toDateString(),
            'scheduled_amount_minor' => 25000,
            'contribution_frequency' => 'monthly',
        ])->assertCreated();
        $goalId = (int) $goalResponse->json('data.goal.id');

        $this->patchJson("/api/savings/goals/{$goalId}/schedule", [
            'scheduled_amount_minor' => 25000,
            'contribution_frequency' => 'monthly',
            'autopilot_enabled' => true,
        ])->assertStatus(409);

        $first = $this->postJson("/api/savings/goals/{$goalId}/contributions", [
            'amount_minor' => 50000,
            'idempotency_key' => 'save-contribution-001',
        ])->assertStatus(202);
        $movementId = (int) $first->json('data.movement.id');
        $mobileMoneyId = (int) $first->json('data.movement.mobile_money_transaction_id');

        $duplicate = $this->postJson("/api/savings/goals/{$goalId}/contributions", [
            'amount_minor' => 50000,
            'idempotency_key' => 'save-contribution-001',
        ])->assertStatus(202);
        $duplicate->assertJsonPath('data.movement.id', $movementId);
        $this->assertDatabaseCount('savings_movements', 1);

        MobileMoneyTransaction::query()->whereKey($mobileMoneyId)->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-save-success-001',
        ]);
        app(SavingsService::class)->syncMobileMoney(MobileMoneyTransaction::findOrFail($mobileMoneyId));

        $this->assertDatabaseHas('savings_movements', [
            'id' => $movementId,
            'status' => SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER,
        ]);
        $this->getJson("/api/savings/goals/{$goalId}")
            ->assertOk()
            ->assertJsonPath('data.goal.confirmed_balance_minor', 0)
            ->assertJsonPath('data.goal.available_balance_minor', 0);

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/savings-movements/{$movementId}/confirm-contribution", [
            'partner_reference' => 'PARTNER-SAV-0001',
            'partner_evidence_hash' => hash('sha256', 'partner savings confirmation'),
        ])->assertOk()
            ->assertJsonPath('data.movement.status', SavingsMovement::STATUS_CONFIRMED);

        Sanctum::actingAs($customer);
        $this->getJson("/api/savings/goals/{$goalId}")
            ->assertOk()
            ->assertJsonPath('data.goal.confirmed_balance_minor', 50000)
            ->assertJsonPath('data.goal.available_balance_minor', 50000);

        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'savings.contribution_collected',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'savings.partner_settled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'savings.product.activated',
            'actor_id' => $checker->id,
        ]);
    }

    public function test_savings_withdrawal_reserves_partner_position_and_pays_only_after_partner_release(): void
    {
        [$customer, $maker, $checker] = $this->actors();
        $product = $this->approvedSavingsProduct($maker, $checker);

        Sanctum::actingAs($customer);
        $goalId = (int) $this->postJson('/api/savings/goals', [
            'savings_product_id' => $product->id,
            'name' => 'School fees',
            'target_amount_minor' => 100000,
        ])->assertCreated()->json('data.goal.id');

        $contribution = $this->postJson("/api/savings/goals/{$goalId}/contributions", [
            'amount_minor' => 60000,
            'idempotency_key' => 'withdraw-seed-001',
        ])->assertStatus(202);
        $movement = SavingsMovement::findOrFail((int) $contribution->json('data.movement.id'));
        $collection = $movement->mobileMoneyTransaction;
        $collection->update(['status' => MobileMoneyTransaction::STATUS_SUCCESSFUL]);
        app(SavingsService::class)->syncMobileMoney($collection->fresh());

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/savings-movements/{$movement->id}/confirm-contribution", [
            'partner_reference' => 'PARTNER-SAV-SEED',
            'partner_evidence_hash' => hash('sha256', 'seed confirmed'),
        ])->assertOk();

        Sanctum::actingAs($customer);
        $withdrawalResponse = $this->postJson("/api/savings/goals/{$goalId}/withdrawals", [
            'amount_minor' => 20000,
            'idempotency_key' => 'withdraw-request-001',
        ])->assertStatus(202)
            ->assertJsonPath('data.next_state', 'partner_release_required');
        $withdrawalId = (int) $withdrawalResponse->json('data.movement.id');

        $this->getJson("/api/savings/goals/{$goalId}")
            ->assertOk()
            ->assertJsonPath('data.goal.confirmed_balance_minor', 60000)
            ->assertJsonPath('data.goal.reserved_withdrawal_minor', 20000)
            ->assertJsonPath('data.goal.available_balance_minor', 40000);

        Sanctum::actingAs($checker);
        $release = $this->postJson("/api/admin/savings-movements/{$withdrawalId}/release-withdrawal", [
            'partner_reference' => 'PARTNER-WDL-0001',
            'partner_evidence_hash' => hash('sha256', 'withdrawal released'),
        ])->assertOk();
        $payoutId = (int) $release->json('data.movement.mobile_money_transaction_id');
        $this->assertDatabaseHas('savings_movements', [
            'id' => $withdrawalId,
            'status' => SavingsMovement::STATUS_PAYOUT_PENDING,
        ]);

        MobileMoneyTransaction::query()->whereKey($payoutId)->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-save-payout-001',
        ]);
        app(SavingsService::class)->syncMobileMoney(MobileMoneyTransaction::findOrFail($payoutId));

        Sanctum::actingAs($customer);
        $this->getJson("/api/savings/goals/{$goalId}")
            ->assertOk()
            ->assertJsonPath('data.goal.confirmed_balance_minor', 40000)
            ->assertJsonPath('data.goal.reserved_withdrawal_minor', 0)
            ->assertJsonPath('data.goal.available_balance_minor', 40000);
        $this->assertDatabaseHas('savings_movements', [
            'id' => $withdrawalId,
            'status' => SavingsMovement::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'savings.withdrawal_partner_release',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'savings.withdrawal_paid',
        ]);
    }

    public function test_protection_requires_disclosure_acceptance_partner_premium_confirmation_and_insurer_issuance(): void
    {
        [$customer, $maker, $checker] = $this->actors();

        Sanctum::actingAs($maker);
        $created = $this->postJson('/api/admin/protection-products', $this->protectionProductPayload())
            ->assertCreated()
            ->assertJsonPath('data.product.status', ProtectionProduct::STATUS_DRAFT);
        $productId = (int) $created->json('data.product.id');

        $this->postJson("/api/admin/protection-products/{$productId}/activate", $this->approvalPayload('PROTECT-MAKER'))
            ->assertStatus(409);

        Sanctum::actingAs($checker);
        $activated = $this->postJson("/api/admin/protection-products/{$productId}/activate", $this->approvalPayload('PROTECT-CHECKER'))
            ->assertOk()
            ->assertJsonPath('data.product.status', ProtectionProduct::STATUS_ACTIVE);
        $disclosureHash = (string) $activated->json('data.disclosure_hash');

        Sanctum::actingAs($customer);
        $this->getJson('/api/protection/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.insurer_name', 'Example Regulated Insurer');

        $this->postJson("/api/protection/products/{$productId}/enroll", [
            'accept_disclosures' => true,
            'disclosure_hash' => str_repeat('0', 64),
        ])->assertStatus(409);

        $enrollment = $this->postJson("/api/protection/products/{$productId}/enroll", [
            'accept_disclosures' => true,
            'disclosure_hash' => $disclosureHash,
        ])->assertCreated()
            ->assertJsonPath('data.policy.status', ProtectionPolicy::STATUS_PREMIUM_DUE);
        $policyId = (int) $enrollment->json('data.policy.id');

        $premium = $this->postJson("/api/protection/policies/{$policyId}/premiums", [
            'idempotency_key' => 'premium-payment-001',
        ])->assertStatus(202);
        $premiumId = (int) $premium->json('data.premium_payment.id');
        $mobileMoneyId = (int) $premium->json('data.premium_payment.mobile_money_transaction_id');

        $this->postJson("/api/protection/policies/{$policyId}/premiums", [
            'idempotency_key' => 'premium-payment-001',
        ])->assertStatus(202)
            ->assertJsonPath('data.premium_payment.id', $premiumId);

        MobileMoneyTransaction::query()->whereKey($mobileMoneyId)->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-premium-success-001',
        ]);
        app(ProtectionService::class)->syncMobileMoney(MobileMoneyTransaction::findOrFail($mobileMoneyId));

        $this->assertDatabaseHas('protection_premium_payments', [
            'id' => $premiumId,
            'status' => ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER,
        ]);
        $this->assertDatabaseHas('protection_policies', [
            'id' => $policyId,
            'status' => ProtectionPolicy::STATUS_PREMIUM_PENDING,
        ]);

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/protection-premiums/{$premiumId}/confirm", [
            'partner_reference' => 'INS-PREM-0001',
            'partner_evidence_hash' => hash('sha256', 'premium settled to insurer'),
        ])->assertOk()
            ->assertJsonPath('data.premium_payment.status', ProtectionPremiumPayment::STATUS_CONFIRMED);
        $this->assertDatabaseHas('protection_policies', [
            'id' => $policyId,
            'status' => ProtectionPolicy::STATUS_PENDING_ISSUANCE,
        ]);

        $this->postJson("/api/admin/protection-policies/{$policyId}/issue", [
            'external_policy_number' => 'INS-POL-2026-0001',
            'partner_reference' => 'INS-ISSUE-0001',
            'cover_start_date' => now()->toDateString(),
            'cover_end_date' => now()->addYear()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.policy.status', ProtectionPolicy::STATUS_ACTIVE)
            ->assertJsonPath('data.policy.external_policy_number', 'INS-POL-2026-0001');

        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'protection.premium_collected',
        ]);
        $this->assertDatabaseHas('ledger_transactions', [
            'event_type' => 'protection.premium_partner_settled',
        ]);
    }

    public function test_protection_claim_decision_stays_with_partner_and_customer_can_dispute_decline(): void
    {
        [$customer, $maker, $checker] = $this->actors();
        $policy = $this->activeProtectionPolicy($customer, $maker, $checker);

        Sanctum::actingAs($customer);
        $claimResponse = $this->postJson("/api/protection/policies/{$policy->id}/claims", [
            'incident_date' => now()->toDateString(),
            'category' => 'medical',
            'description' => 'Customer received eligible emergency medical treatment.',
            'claimed_amount_minor' => 75000,
            'evidence' => ['receipt:sha256:abc123', 'medical-note:sha256:def456'],
        ])->assertCreated()
            ->assertJsonPath('data.decision_authority', 'insurer_or_underwriter');
        $claimId = (int) $claimResponse->json('data.claim.id');

        Sanctum::actingAs($checker);
        $this->patchJson("/api/admin/protection-claims/{$claimId}", [
            'status' => ProtectionClaim::STATUS_APPROVED,
            'partner_claim_reference' => 'INS-CLM-0001',
            'approved_amount_minor' => 50000,
        ])->assertStatus(409);

        $this->patchJson("/api/admin/protection-claims/{$claimId}", [
            'status' => ProtectionClaim::STATUS_PARTNER_REVIEW,
            'partner_claim_reference' => 'INS-CLM-0001',
        ])->assertOk();

        $this->patchJson("/api/admin/protection-claims/{$claimId}", [
            'status' => ProtectionClaim::STATUS_DECLINED,
            'partner_claim_reference' => 'INS-CLM-0001',
        ])->assertStatus(409);

        $this->patchJson("/api/admin/protection-claims/{$claimId}", [
            'status' => ProtectionClaim::STATUS_DECLINED,
            'partner_claim_reference' => 'INS-CLM-0001',
            'decision_reason' => 'Insurer determined the submitted evidence did not satisfy the covered-event definition.',
        ])->assertOk()
            ->assertJsonPath('data.claim.status', ProtectionClaim::STATUS_DECLINED);

        Sanctum::actingAs($customer);
        $this->postJson("/api/protection/claims/{$claimId}/dispute", [
            'reason' => 'The customer disputes the interpretation and requests insurer reconsideration of the submitted evidence.',
        ])->assertOk()
            ->assertJsonPath('data.claim.status', ProtectionClaim::STATUS_DISPUTED);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'protection.claim.partner_status_updated',
            'actor_id' => $checker->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'protection.claim.disputed',
            'actor_id' => $customer->id,
        ]);
    }

    public function test_customers_cannot_access_each_others_save_or_protection_records(): void
    {
        [$customer, $maker, $checker, $otherCustomer] = $this->actors(includeSecondCustomer: true);
        $savings = $this->approvedSavingsProduct($maker, $checker);
        $policy = $this->activeProtectionPolicy($customer, $maker, $checker);

        Sanctum::actingAs($customer);
        $goalId = (int) $this->postJson('/api/savings/goals', [
            'savings_product_id' => $savings->id,
            'name' => 'Private goal',
        ])->assertCreated()->json('data.goal.id');

        Sanctum::actingAs($otherCustomer);
        $this->getJson("/api/savings/goals/{$goalId}")->assertForbidden();
        $this->getJson("/api/protection/policies/{$policy->id}")->assertForbidden();
    }

    private function actors(bool $includeSecondCustomer = false): array
    {
        $institution = Institution::create([
            'name' => 'Save Protect Institution',
            'address' => 'Kampala',
            'phone' => '256700009001',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => '256700009002',
        ]);
        $maker = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        $checker = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);

        if (!$includeSecondCustomer) {
            return [$customer, $maker, $checker];
        }

        $otherCustomer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => '256700009003',
        ]);

        return [$customer, $maker, $checker, $otherCustomer];
    }

    private function approvedSavingsProduct(User $maker, User $checker): SavingsProduct
    {
        Sanctum::actingAs($maker);
        $productId = (int) $this->postJson('/api/admin/savings-products', $this->savingsProductPayload())
            ->assertCreated()
            ->json('data.product.id');

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/savings-products/{$productId}/activate", $this->approvalPayload('SAVE-APPROVED'))
            ->assertOk();

        return SavingsProduct::findOrFail($productId);
    }

    private function activeProtectionPolicy(User $customer, User $maker, User $checker): ProtectionPolicy
    {
        Sanctum::actingAs($maker);
        $productId = (int) $this->postJson('/api/admin/protection-products', $this->protectionProductPayload())
            ->assertCreated()
            ->json('data.product.id');

        Sanctum::actingAs($checker);
        $activation = $this->postJson("/api/admin/protection-products/{$productId}/activate", $this->approvalPayload('PROTECT-APPROVED'))
            ->assertOk();
        $disclosureHash = (string) $activation->json('data.disclosure_hash');

        Sanctum::actingAs($customer);
        $policyId = (int) $this->postJson("/api/protection/products/{$productId}/enroll", [
            'accept_disclosures' => true,
            'disclosure_hash' => $disclosureHash,
        ])->assertCreated()->json('data.policy.id');
        $premium = $this->postJson("/api/protection/policies/{$policyId}/premiums", [
            'idempotency_key' => 'policy-helper-premium-001-'.$policyId,
        ])->assertStatus(202);
        $premiumId = (int) $premium->json('data.premium_payment.id');
        $mobileMoneyId = (int) $premium->json('data.premium_payment.mobile_money_transaction_id');
        MobileMoneyTransaction::whereKey($mobileMoneyId)->update(['status' => MobileMoneyTransaction::STATUS_SUCCESSFUL]);
        app(ProtectionService::class)->syncMobileMoney(MobileMoneyTransaction::findOrFail($mobileMoneyId));

        Sanctum::actingAs($checker);
        $this->postJson("/api/admin/protection-premiums/{$premiumId}/confirm", [
            'partner_reference' => 'INS-PREM-HELPER-'.$policyId,
            'partner_evidence_hash' => hash('sha256', 'helper premium '.$policyId),
        ])->assertOk();
        $this->postJson("/api/admin/protection-policies/{$policyId}/issue", [
            'external_policy_number' => 'INS-POL-HELPER-'.$policyId,
            'partner_reference' => 'INS-ISSUE-HELPER-'.$policyId,
            'cover_start_date' => now()->subDay()->toDateString(),
            'cover_end_date' => now()->addYear()->toDateString(),
        ])->assertOk();

        return ProtectionPolicy::findOrFail($policyId);
    }

    private function savingsProductPayload(): array
    {
        return [
            'code' => 'SAVE-GOAL-'.fake()->unique()->numerify('####'),
            'name' => 'Partner Goal Savings',
            'partner_name' => 'Example Regulated Savings Partner',
            'partner_product_reference' => 'PARTNER-SAVE-001',
            'country_code' => 'UG',
            'currency' => 'UGX',
            'product_type' => 'goal',
            'custody_model' => 'partner_held',
            'minimum_contribution_minor' => 1000,
            'maximum_contribution_minor' => 1000000,
            'minimum_withdrawal_minor' => 1000,
            'notice_days' => 0,
            'lock_days' => 0,
            'terms_version' => 'save-terms-v1',
            'terms_url' => 'https://example.test/savings/terms-v1',
            'disclosures' => [
                'Funds are held by the disclosed savings partner.',
                'OpFin does not present this product as an OpFin stored-value wallet.',
            ],
        ];
    }

    private function protectionProductPayload(): array
    {
        return [
            'code' => 'PROTECT-'.fake()->unique()->numerify('####'),
            'name' => 'Family Emergency Cover',
            'insurer_name' => 'Example Regulated Insurer',
            'underwriter_name' => 'Example Underwriter',
            'partner_product_reference' => 'INS-PRODUCT-001',
            'country_code' => 'UG',
            'currency' => 'UGX',
            'product_type' => 'health',
            'premium_amount_minor' => 10000,
            'premium_frequency' => 'monthly',
            'coverage_limit_minor' => 500000,
            'disclosure_version' => 'protect-v1',
            'benefits' => ['Emergency medical reimbursement up to the policy limit'],
            'exclusions' => ['Events outside the issued cover period'],
            'disclosure_payload' => [
                'decision_authority' => 'insurer_or_underwriter',
                'claims' => 'Claims are assessed by the disclosed insurer or underwriter.',
            ],
            'terms_url' => 'https://example.test/protection/terms-v1',
        ];
    }

    private function approvalPayload(string $reference): array
    {
        return [
            'approval_reference' => $reference,
            'approval_evidence_hash' => hash('sha256', 'approval evidence '.$reference),
            'approval_note' => 'Independent operations review completed with controlled product and partner evidence.',
        ];
    }
}
