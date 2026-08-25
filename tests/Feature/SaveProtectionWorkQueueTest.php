<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\ProtectionProduct;
use App\Models\SavingsGoal;
use App\Models\SavingsMovement;
use App\Models\SavingsProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveProtectionWorkQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_queue_is_institution_scoped_and_exposes_actionable_records(): void
    {
        [$institutionA, $operations, $customerA] = $this->institutionActors('A', User::ROLE_OPERATIONS);
        [$institutionB, , $customerB] = $this->institutionActors('B', User::ROLE_OPERATIONS);

        $savingsProduct = SavingsProduct::create([
            'code' => 'QUEUE-SAVE',
            'name' => 'Queue Savings',
            'partner_name' => 'Queue Savings Partner',
            'partner_product_reference' => 'QUEUE-SAV-001',
            'status' => SavingsProduct::STATUS_ACTIVE,
            'custody_model' => 'partner_held',
            'disclosures' => ['Partner held'],
            'terms_url' => 'https://example.test/savings',
            'approved_by' => $operations->id,
            'approved_at' => now(),
        ]);

        $goalA = $this->savingsGoal($customerA, $institutionA, $savingsProduct, 'QUEUE-GOAL-A');
        $goalB = $this->savingsGoal($customerB, $institutionB, $savingsProduct, 'QUEUE-GOAL-B');

        SavingsMovement::create([
            'savings_goal_id' => $goalA->id,
            'user_id' => $customerA->id,
            'institution_id' => $institutionA->id,
            'movement_reference' => 'QUEUE-SAV-CONTRIBUTION-A',
            'movement_type' => SavingsMovement::TYPE_CONTRIBUTION,
            'status' => SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER,
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'idempotency_key' => 'queue-contribution-a',
            'requested_at' => now()->subMinutes(15),
        ]);
        SavingsMovement::create([
            'savings_goal_id' => $goalA->id,
            'user_id' => $customerA->id,
            'institution_id' => $institutionA->id,
            'movement_reference' => 'QUEUE-SAV-WITHDRAWAL-A',
            'movement_type' => SavingsMovement::TYPE_WITHDRAWAL,
            'status' => SavingsMovement::STATUS_WITHDRAWAL_REQUESTED,
            'amount_minor' => 10000,
            'currency' => 'UGX',
            'idempotency_key' => 'queue-withdrawal-a',
            'requested_at' => now()->subMinutes(10),
        ]);
        SavingsMovement::create([
            'savings_goal_id' => $goalB->id,
            'user_id' => $customerB->id,
            'institution_id' => $institutionB->id,
            'movement_reference' => 'QUEUE-SAV-CONTRIBUTION-B',
            'movement_type' => SavingsMovement::TYPE_CONTRIBUTION,
            'status' => SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER,
            'amount_minor' => 75000,
            'currency' => 'UGX',
            'idempotency_key' => 'queue-contribution-b',
            'requested_at' => now()->subMinutes(5),
        ]);

        [$policy, $premium, $claim] = $this->protectionQueueRecords($customerA, $institutionA, $operations);

        Sanctum::actingAs($operations);
        $response = $this->getJson('/api/admin/save-protection/work-queue')
            ->assertOk()
            ->assertJsonPath('data.scope', 'institution')
            ->assertJsonPath('data.institution_id', $institutionA->id)
            ->assertJsonPath('data.counts.savings_contributions', 1)
            ->assertJsonPath('data.counts.savings_withdrawals', 1)
            ->assertJsonPath('data.counts.protection_premiums', 1)
            ->assertJsonPath('data.counts.protection_policies', 1)
            ->assertJsonPath('data.counts.protection_claims', 1);

        $response
            ->assertJsonPath('data.savings_contributions.0.movement_reference', 'QUEUE-SAV-CONTRIBUTION-A')
            ->assertJsonPath('data.savings_withdrawals.0.movement_reference', 'QUEUE-SAV-WITHDRAWAL-A')
            ->assertJsonPath('data.protection_premiums.0.id', $premium->id)
            ->assertJsonPath('data.protection_policies.0.id', $policy->id)
            ->assertJsonPath('data.protection_claims.0.id', $claim->id);

        $this->assertStringNotContainsString('QUEUE-SAV-CONTRIBUTION-B', $response->getContent());
    }

    public function test_platform_admin_sees_cross_institution_queue_and_customer_is_forbidden(): void
    {
        [$institutionA, , $customerA] = $this->institutionActors('A', User::ROLE_OPERATIONS);
        [$institutionB, , $customerB] = $this->institutionActors('B', User::ROLE_OPERATIONS);
        $platformAdmin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'institution_id' => $institutionA->id,
        ]);

        $product = SavingsProduct::create([
            'code' => 'QUEUE-PLATFORM-SAVE',
            'name' => 'Platform Queue Savings',
            'partner_name' => 'Queue Savings Partner',
            'status' => SavingsProduct::STATUS_ACTIVE,
            'custody_model' => 'partner_held',
            'approved_by' => $platformAdmin->id,
            'approved_at' => now(),
        ]);

        foreach ([[$institutionA, $customerA, 'A'], [$institutionB, $customerB, 'B']] as [$institution, $customer, $suffix]) {
            $goal = $this->savingsGoal($customer, $institution, $product, "QUEUE-PLATFORM-GOAL-{$suffix}");
            SavingsMovement::create([
                'savings_goal_id' => $goal->id,
                'user_id' => $customer->id,
                'institution_id' => $institution->id,
                'movement_reference' => "QUEUE-PLATFORM-CONTRIBUTION-{$suffix}",
                'movement_type' => SavingsMovement::TYPE_CONTRIBUTION,
                'status' => SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER,
                'amount_minor' => 25000,
                'currency' => 'UGX',
                'idempotency_key' => "queue-platform-{$suffix}",
                'requested_at' => now(),
            ]);
        }

        Sanctum::actingAs($platformAdmin);
        $this->getJson('/api/admin/save-protection/work-queue')
            ->assertOk()
            ->assertJsonPath('data.scope', 'platform')
            ->assertJsonPath('data.institution_id', null)
            ->assertJsonPath('data.counts.savings_contributions', 2);

        Sanctum::actingAs($customerA);
        $this->getJson('/api/admin/save-protection/work-queue')->assertForbidden();
    }

    private function institutionActors(string $suffix, string $operationsRole): array
    {
        $institution = Institution::create([
            'name' => "Queue Institution {$suffix}",
            'address' => 'Kampala',
            'phone' => "25670000800{$suffix}",
            'email' => strtolower("queue-{$suffix}@example.test"),
        ]);
        $operations = User::factory()->create([
            'role' => $operationsRole,
            'institution_id' => $institution->id,
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
        ]);

        return [$institution, $operations, $customer];
    }

    private function savingsGoal(User $customer, Institution $institution, SavingsProduct $product, string $reference): SavingsGoal
    {
        return SavingsGoal::create([
            'user_id' => $customer->id,
            'institution_id' => $institution->id,
            'savings_product_id' => $product->id,
            'goal_reference' => $reference,
            'name' => 'Queue goal',
            'status' => SavingsGoal::STATUS_ACTIVE,
        ]);
    }

    private function protectionQueueRecords(User $customer, Institution $institution, User $approver): array
    {
        $product = ProtectionProduct::create([
            'code' => 'QUEUE-PROTECTION',
            'name' => 'Queue Protection',
            'insurer_name' => 'Queue Insurer',
            'underwriter_name' => 'Queue Underwriter',
            'partner_product_reference' => 'QUEUE-PROTECT-001',
            'product_type' => 'health',
            'status' => ProtectionProduct::STATUS_ACTIVE,
            'premium_amount_minor' => 10000,
            'premium_frequency' => 'monthly',
            'disclosure_payload' => ['claims' => 'Partner decides'],
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
        $policy = ProtectionPolicy::create([
            'protection_product_id' => $product->id,
            'user_id' => $customer->id,
            'institution_id' => $institution->id,
            'policy_reference' => 'QUEUE-POLICY-A',
            'status' => ProtectionPolicy::STATUS_PENDING_ISSUANCE,
            'premium_amount_minor' => 10000,
            'premium_frequency' => 'monthly',
            'disclosure_hash' => str_repeat('a', 64),
            'enrolled_at' => now()->subHour(),
        ]);
        $premium = ProtectionPremiumPayment::create([
            'protection_policy_id' => $policy->id,
            'user_id' => $customer->id,
            'institution_id' => $institution->id,
            'payment_reference' => 'QUEUE-PREMIUM-A',
            'idempotency_key' => 'queue-premium-a',
            'status' => ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER,
            'amount_minor' => 10000,
            'currency' => 'UGX',
            'requested_at' => now()->subMinutes(20),
        ]);
        $claim = ProtectionClaim::create([
            'protection_policy_id' => $policy->id,
            'user_id' => $customer->id,
            'institution_id' => $institution->id,
            'claim_reference' => 'QUEUE-CLAIM-A',
            'status' => ProtectionClaim::STATUS_SUBMITTED,
            'incident_date' => now()->subDay()->toDateString(),
            'category' => 'medical',
            'description' => 'Queue claim awaiting insurer review.',
            'submitted_at' => now()->subMinutes(30),
        ]);

        return [$policy, $premium, $claim];
    }
}
