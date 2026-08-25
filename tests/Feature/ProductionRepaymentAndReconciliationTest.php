<?php

namespace Tests\Feature;

use App\Models\CreditDecision;
use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\ReconciliationItem;
use App\Models\User;
use App\Services\ProductionCreditOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionRepaymentAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repayment_requires_idempotency_and_replays_same_collection_once(): void
    {
        [$customer, , $loan] = $this->productionLoan();
        Sanctum::actingAs($customer);

        $this->postJson("/api/loans/{$loan->id}/repay", [
            'amount_minor' => 50000,
        ])->assertStatus(422);

        $first = $this->withHeader('Idempotency-Key', 'repayment-key-001')
            ->postJson("/api/loans/{$loan->id}/repay", [
                'amount_minor' => 50000,
            ]);

        $first
            ->assertStatus(202)
            ->assertJsonPath('data.status', MobileMoneyTransaction::STATUS_PENDING)
            ->assertJsonPath('data.amount_minor', 50000);

        $reference = $first->json('data.reference');

        $this->withHeader('Idempotency-Key', 'repayment-key-001')
            ->postJson("/api/loans/{$loan->id}/repay", [
                'amount_minor' => 50000,
            ])
            ->assertStatus(202)
            ->assertJsonPath('data.reference', $reference);

        $this->assertDatabaseCount('mobile_money_transactions', 2); // one payout plus one repayment
        $this->assertDatabaseCount('transactions', 1);

        $this->withHeader('Idempotency-Key', 'repayment-key-002')
            ->postJson("/api/loans/{$loan->id}/repay", [
                'amount_minor' => 10000,
            ])
            ->assertStatus(409);
    }

    public function test_successful_collection_reduces_exact_production_schedule_once(): void
    {
        [$customer, $operations, $loan] = $this->productionLoan();
        Sanctum::actingAs($customer);

        $this->withHeader('Idempotency-Key', 'repayment-success-001')
            ->postJson("/api/loans/{$loan->id}/repay", [
                'amount_minor' => 50000,
            ])
            ->assertStatus(202);

        $repayment = MobileMoneyTransaction::query()
            ->where('direction', MobileMoneyTransaction::DIRECTION_COLLECTION)
            ->firstOrFail();
        $repayment->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-collection-success-001',
        ]);

        Sanctum::actingAs($operations);
        $this->postJson("/api/admin/mobile-money-transactions/{$repayment->id}/refresh-status")
            ->assertOk()
            ->assertJsonPath('data.transaction.status', MobileMoneyTransaction::STATUS_SUCCESSFUL)
            ->assertJsonPath('data.loan_id', $loan->id);

        $remaining = (int) $this->app['db']->table('credit_repayment_schedule_items')
            ->where('loan_id', $loan->id)
            ->sum('total_outstanding_minor');
        $this->assertSame(120000, $remaining);

        $ledger = $this->app['db']->table('ledger_transactions')
            ->where('event_type', 'loan.repayment')
            ->first();
        $this->assertNotNull($ledger);
        $this->assertDatabaseHas('ledger_accounts', [
            'code' => 'cash.mock.collection',
        ]);
        $this->assertDatabaseHas('ledger_accounts', [
            'code' => 'liability.credit_fee_clearing.product_'.$loan->loan_product_id,
        ]);

        $this->postJson("/api/admin/mobile-money-transactions/{$repayment->id}/refresh-status")
            ->assertOk();

        $remainingAfterReplay = (int) $this->app['db']->table('credit_repayment_schedule_items')
            ->where('loan_id', $loan->id)
            ->sum('total_outstanding_minor');
        $this->assertSame(120000, $remainingAfterReplay);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'credit.repayment.fulfilled',
            'subject_id' => $loan->id,
        ]);
    }

    public function test_repayment_rejects_amount_above_exact_production_obligation(): void
    {
        [$customer, , $loan] = $this->productionLoan();
        Sanctum::actingAs($customer);

        $this->withHeader('Idempotency-Key', 'repayment-overpay-001')
            ->postJson("/api/loans/{$loan->id}/repay", [
                'amount_minor' => 170001,
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(1, MobileMoneyTransaction::query()->count()); // disbursement only
    }

    public function test_reconciliation_is_business_date_scoped_and_classifies_provider_evidence(): void
    {
        $institution = Institution::create([
            'name' => 'Reconciliation Institution',
            'address' => 'Kampala',
            'phone' => '256700001100',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);

        $matched = $this->systemPayment($institution, 'rec-match-1', 'provider-match-1', 50000, MobileMoneyTransaction::STATUS_SUCCESSFUL);
        $mismatch = $this->systemPayment($institution, 'rec-mismatch-1', 'provider-mismatch-1', 60000, MobileMoneyTransaction::STATUS_SUCCESSFUL);
        $missingProvider = $this->systemPayment($institution, 'rec-missing-provider-1', 'provider-missing-1', 70000, MobileMoneyTransaction::STATUS_PENDING);
        $yesterday = $this->systemPayment($institution, 'rec-yesterday-1', 'provider-yesterday-1', 80000, MobileMoneyTransaction::STATUS_SUCCESSFUL);
        $yesterday->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        Sanctum::actingAs($operations);
        $runResponse = $this->postJson('/api/admin/reconciliation-runs', [
            'provider' => 'cpay',
            'business_date' => now()->toDateString(),
        ]);

        $runResponse
            ->assertCreated()
            ->assertJsonPath('data.item_count', 3);
        $runId = (int) $runResponse->json('data.run.id');

        $this->postJson("/api/admin/reconciliation-runs/{$runId}/provider-records", [
            'records' => [
                [
                    'provider_reference' => $matched->provider_reference,
                    'internal_reference' => $matched->internal_reference,
                    'amount_minor' => 50000,
                    'currency' => 'UGX',
                    'direction' => 'collection',
                    'provider_status' => 'SUCCESSFUL',
                ],
                [
                    'provider_reference' => $mismatch->provider_reference,
                    'internal_reference' => $mismatch->internal_reference,
                    'amount_minor' => 60001,
                    'currency' => 'UGX',
                    'direction' => 'collection',
                    'provider_status' => 'SUCCESSFUL',
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('reconciliation_items', [
            'mobile_money_transaction_id' => $matched->id,
            'status' => ReconciliationItem::STATUS_MATCHED,
            'exception_type' => null,
        ]);
        $this->assertDatabaseHas('reconciliation_items', [
            'mobile_money_transaction_id' => $mismatch->id,
            'status' => ReconciliationItem::STATUS_EXCEPTION,
            'exception_type' => ReconciliationItem::EXCEPTION_AMOUNT_MISMATCH,
        ]);
        $this->assertDatabaseMissing('reconciliation_items', [
            'mobile_money_transaction_id' => $yesterday->id,
        ]);

        $complete = $this->postJson("/api/admin/reconciliation-runs/{$runId}/complete")
            ->assertOk();

        $complete
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.summary.matched_count', 1)
            ->assertJsonPath('data.run.summary.exception_count', 2)
            ->assertJsonPath('data.run.summary.pending_provider_match_count', 0);

        $this->assertDatabaseHas('reconciliation_items', [
            'mobile_money_transaction_id' => $missingProvider->id,
            'status' => ReconciliationItem::STATUS_EXCEPTION,
            'exception_type' => ReconciliationItem::EXCEPTION_MISSING_PROVIDER_RECORD,
        ]);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'id' => $matched->id,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
        ]);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'id' => $mismatch->id,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
        ]);
    }

    private function productionLoan(): array
    {
        $institution = Institution::create([
            'name' => 'Repayment Institution',
            'address' => 'Kampala',
            'phone' => '256700000901',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => '256700000902',
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        $product = LoanProduct::create([
            'name' => 'Repayment Credit',
            'type' => 'Cash',
            'institution_id' => $institution->id,
        ]);
        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 30,
            'status' => 'Active',
        ]);
        $application = LoanApplication::create([
            'user_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 150000,
            'status' => 'Approved',
            'reason' => 'Working capital',
            'approved_at' => now(),
        ]);
        CreditDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $customer->id,
            'decided_by' => $operations->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'requested_amount_minor' => 150000,
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 600000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'ug-credit-policy-2026-08',
            'reason_codes' => ['AFFORDABILITY_REVIEWED'],
            'decision_summary' => 'Approved for repayment hardening test.',
            'decided_at' => now(),
        ]);

        $offers = app(ProductionCreditOfferService::class);
        $offer = $offers->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
        ]);
        $accepted = $offers->acceptOffer($offer, $customer, ['channel' => 'test']);
        $payout = $accepted['mobile_money'];
        $payout->update([
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'provider_reference' => 'mock-payout-'.$offer->id,
        ]);
        $loan = $offers->syncDisbursementState($payout->fresh());

        $this->assertInstanceOf(Loan::class, $loan);

        return [$customer, $operations, $loan];
    }

    private function systemPayment(
        Institution $institution,
        string $internalReference,
        string $providerReference,
        int $amountMinor,
        string $status,
    ): MobileMoneyTransaction {
        return MobileMoneyTransaction::create([
            'user_id' => null,
            'institution_id' => $institution->id,
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => $amountMinor,
            'currency' => 'UGX',
            'phone' => '256700001101',
            'idempotency_key' => 'idem-'.$internalReference,
            'internal_reference' => $internalReference,
            'provider_reference' => $providerReference,
            'status' => $status,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);
    }
}
