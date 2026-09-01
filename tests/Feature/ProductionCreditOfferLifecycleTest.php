<?php

namespace Tests\Feature;

use App\Models\CreditDecision;
use App\Models\CreditOffer;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\Institution;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\ProductionCreditOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionCreditOfferLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_can_approve_referred_decision_and_generate_immutable_offer(): void
    {
        [$customer, $operations, $application, $decision] = $this->referredApplication();
        Sanctum::actingAs($operations);

        $this->postJson("/api/admin/credit-decisions/{$decision->id}/approve", [
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 600000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'ug-credit-policy-2026-08',
            'reason_codes' => ['AFFORDABILITY_REVIEWED', 'CRB_CLEAR'],
            'decision_summary' => 'Approved after documented affordability and operations review.',
        ])->assertOk()->assertJsonPath('data.decision.status', CreditDecision::STATUS_APPROVED)
            ->assertJsonPath('data.next_state', 'offer_generation');

        $offerResponse = $this->postJson("/api/admin/credit/applications/{$application->id}/offer", [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
        ]);

        $offerResponse->assertCreated()
            ->assertJsonPath('data.offer.status', CreditOffer::STATUS_OFFERED)
            ->assertJsonPath('data.offer.principal_amount_minor', 150000)
            ->assertJsonPath('data.offer.interest_amount_minor', 15000)
            ->assertJsonPath('data.offer.fees_minor', 5000)
            ->assertJsonPath('data.offer.net_disbursement_minor', 150000)
            ->assertJsonPath('data.offer.total_repayment_minor', 170000)
            ->assertJsonPath('data.offer.policy_version', 'ug-credit-policy-2026-08');

        $this->assertSame(64, strlen((string) $offerResponse->json('data.disclosure_hash')));
        $this->assertDatabaseHas('audit_logs', ['event' => 'credit.offer.created', 'actor_id' => $operations->id]);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseHas('credit_offers', [
            'user_id' => $customer->id,
            'loan_application_id' => $application->id,
            'version' => 1,
            'total_repayment_minor' => 170000,
        ]);
    }

    public function test_customer_acceptance_requires_exact_disclosure_and_fulfills_once_after_success(): void
    {
        [$customer, $operations, $application] = $this->approvedApplication();
        Sanctum::actingAs($operations);

        $offerResponse = $this->postJson("/api/admin/credit/applications/{$application->id}/offer", [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'deducted',
            'expires_in_minutes' => 60,
        ])->assertCreated();

        $offerId = (int) $offerResponse->json('data.offer.id');
        $disclosureHash = (string) $offerResponse->json('data.disclosure_hash');
        Sanctum::actingAs($customer);

        $this->postJson("/api/credit/offers/{$offerId}/accept", [
            'accept_disclosures' => true,
            'disclosure_hash' => str_repeat('0', 64),
        ])->assertStatus(409)->assertJsonPath('errors.disclosure_hash.0', 'DISCLOSURE_HASH_MISMATCH');
        $this->assertDatabaseCount('mobile_money_transactions', 0);

        $acceptance = $this->postJson("/api/credit/offers/{$offerId}/accept", [
            'accept_disclosures' => true,
            'disclosure_hash' => $disclosureHash,
        ]);
        $acceptance->assertOk()
            ->assertJsonPath('data.offer.status', CreditOffer::STATUS_DISBURSEMENT_PENDING)
            ->assertJsonPath('data.mobile_money.status', MobileMoneyTransaction::STATUS_PENDING)
            ->assertJsonPath('data.mobile_money.amount_minor', 145000)
            ->assertJsonPath('data.next_state', 'disbursement_pending');

        $transaction = MobileMoneyTransaction::query()->firstOrFail();
        $transaction->update(['status' => MobileMoneyTransaction::STATUS_SUCCESSFUL, 'provider_reference' => 'cpay-success-001']);

        $service = app(ProductionCreditOfferService::class);
        $loan = $service->syncDisbursementState($transaction->fresh());
        $duplicate = $service->syncDisbursementState($transaction->fresh());

        $this->assertNotNull($loan);
        $this->assertSame($loan->id, $duplicate?->id);
        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseHas('credit_offers', [
            'id' => $offerId,
            'status' => CreditOffer::STATUS_DISBURSED,
            'net_disbursement_minor' => 145000,
            'total_repayment_minor' => 165000,
        ]);
        $this->assertDatabaseHas('loan_applications', ['id' => $application->id, 'status' => 'Disbursed']);

        $ledger = $this->app['db']->table('ledger_transactions')->where('event_type', 'loan.disbursement')->first();
        $this->assertNotNull($ledger);
        $debits = (int) $this->app['db']->table('ledger_entries')->where('ledger_transaction_id', $ledger->id)->where('direction', 'debit')->sum('amount_minor');
        $credits = (int) $this->app['db']->table('ledger_entries')->where('ledger_transaction_id', $ledger->id)->where('direction', 'credit')->sum('amount_minor');
        $this->assertSame(150000, $debits);
        $this->assertSame(150000, $credits);
        $this->assertDatabaseHas('ledger_entries', ['ledger_transaction_id' => $ledger->id, 'direction' => 'credit', 'amount_minor' => 145000]);
        $this->assertDatabaseHas('ledger_entries', ['ledger_transaction_id' => $ledger->id, 'direction' => 'credit', 'amount_minor' => 5000]);

        $scheduledTotal = (int) $this->app['db']->table('credit_repayment_schedule_items')->where('loan_id', $loan->id)->sum('total_due_minor');
        $this->assertSame(165000, $scheduledTotal);
        $this->assertDatabaseHas('audit_logs', ['event' => 'credit.disbursement.fulfilled', 'subject_id' => $loan->id]);
    }

    public function test_provider_reversal_voids_unrepaid_loan_and_posts_append_only_reversal(): void
    {
        [$customer, $operations, $application] = $this->approvedApplication();
        $offer = app(ProductionCreditOfferService::class)->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'deducted',
            'expires_in_minutes' => 60,
        ]);
        $accepted = app(ProductionCreditOfferService::class)->acceptOffer($offer, $customer, ['channel' => 'test']);
        $transaction = $accepted['mobile_money'];
        $transaction->update(['status' => MobileMoneyTransaction::STATUS_SUCCESSFUL, 'provider_reference' => 'cpay-reversal-001']);
        $service = app(ProductionCreditOfferService::class);
        $loan = $service->syncDisbursementState($transaction->fresh());
        $this->assertNotNull($loan);
        $this->assertDatabaseCount('ledger_transactions', 1);

        $transaction->update(['status' => MobileMoneyTransaction::STATUS_REVERSED]);
        $reversedLoan = $service->syncDisbursementState($transaction->fresh());

        $this->assertSame('Reversed', $reversedLoan?->status);
        $this->assertDatabaseCount('ledger_transactions', 2);
        $this->assertDatabaseHas('ledger_transactions', ['event_type' => 'loan.disbursement.reversal']);
        $this->assertSame(0, (int) $this->app['db']->table('credit_repayment_schedule_items')->where('loan_id', $loan->id)->sum('total_outstanding_minor'));
        $this->assertSame(0, $this->app['db']->table('credit_repayment_schedule_items')->where('loan_id', $loan->id)->where('status', '!=', CreditRepaymentScheduleItem::STATUS_VOIDED)->count());

        foreach ($this->app['db']->table('ledger_transactions')->whereIn('event_type', ['loan.disbursement', 'loan.disbursement.reversal'])->get() as $posted) {
            $debits = (int) $this->app['db']->table('ledger_entries')->where('ledger_transaction_id', $posted->id)->where('direction', 'debit')->sum('amount_minor');
            $credits = (int) $this->app['db']->table('ledger_entries')->where('ledger_transaction_id', $posted->id)->where('direction', 'credit')->sum('amount_minor');
            $this->assertSame($debits, $credits);
        }
    }

    public function test_expired_offer_cannot_initiate_money_movement(): void
    {
        [$customer, $operations, $application] = $this->approvedApplication();
        Sanctum::actingAs($operations);
        $offerResponse = $this->postJson("/api/admin/credit/applications/{$application->id}/offer", [
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
        ])->assertCreated();

        $offerId = (int) $offerResponse->json('data.offer.id');
        CreditOffer::query()->whereKey($offerId)->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($customer);
        $freshOffer = $this->getJson("/api/credit/offers/{$offerId}")->assertOk();
        $freshOffer->assertJsonPath('data.offer.status', CreditOffer::STATUS_EXPIRED);
        $this->postJson("/api/credit/offers/{$offerId}/accept", [
            'accept_disclosures' => true,
            'disclosure_hash' => (string) $freshOffer->json('data.disclosure_hash'),
        ])->assertStatus(409);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
        $this->assertDatabaseCount('loans', 0);
    }

    private function approvedApplication(): array
    {
        [$customer, $operations, $application, $decision] = $this->referredApplication();
        $decision->update([
            'status' => CreditDecision::STATUS_APPROVED,
            'approved_amount_minor' => 150000,
            'monthly_income_minor' => 600000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'ug-credit-policy-2026-08',
            'reason_codes' => ['AFFORDABILITY_REVIEWED', 'CRB_CLEAR'],
            'decision_summary' => 'Approved after documented review.',
            'decided_by' => $operations->id,
            'decided_at' => now(),
        ]);
        $application->update(['status' => 'Approved', 'approved_at' => now()]);

        return [$customer, $operations, $application, $decision];
    }

    private function referredApplication(): array
    {
        $institution = Institution::create([
            'name' => 'Offer Lifecycle Institution',
            'address' => 'Kampala',
            'phone' => '256700000601',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'institution_id' => $institution->id]);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'institution_id' => $institution->id]);
        $product = LoanProduct::create(['name' => 'Controlled Credit', 'type' => 'Cash', 'institution_id' => $institution->id]);
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
            'status' => 'Referred',
            'reason' => 'Education expense',
        ]);
        $decision = CreditDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $customer->id,
            'status' => CreditDecision::STATUS_REFERRED,
            'requested_amount_minor' => 150000,
            'approved_amount_minor' => 150000,
            'reason_codes' => ['KYC_VERIFIED', 'CONSENT_GRANTED', 'CRB_CLEAR'],
            'decision_summary' => 'Prerequisite gates passed and operations review is required.',
            'decided_at' => now(),
        ]);

        return [$customer, $operations, $application, $decision];
    }
}
