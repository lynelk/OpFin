<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\CreditDecision;
use App\Models\CreditReferenceSubmission;
use App\Models\CreditTermVariation;
use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\SupportCase;
use App\Models\TransactionReceipt;
use App\Models\User;
use App\Services\CreditReferenceReportingService;
use App\Services\CreditTermVariationService;
use App\Services\NplRecoveryPolicyService;
use App\Services\ProductionCreditOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UmraDigitalLendingControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_offer_disclosure_explains_cost_complaints_reporting_guarantors_and_term_variations(): void
    {
        [, $operations, $application] = $this->approvedApplication(duration: 90, guarantorsRequired: 1);

        $offer = app(ProductionCreditOfferService::class)->createOffer($application, $operations, [
            'access_fee_minor' => 3000,
            'disbursement_fee_minor' => 2000,
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
        ]);

        $disclosure = $offer->disclosure_snapshot;

        $this->assertSame('umra-loan-disclosure-v1', $disclosure['schema_version']);
        $this->assertSame(5000, $disclosure['fees_minor']);
        $this->assertSame(
            $offer->interest_amount_minor + 5000,
            $disclosure['total_cost_of_credit_minor'],
        );
        $this->assertSame(30, $disclosure['complaints']['resolution_sla_days']);
        $this->assertStringContainsString('Term rate', $disclosure['interest']['calculation']);
        $this->assertStringContainsString('Positive and negative', $disclosure['credit_information_exchange']['notice']);
        $this->assertSame(1, $disclosure['guarantors']['required_count']);
        $this->assertSame(2, $disclosure['guarantors']['maximum_contacts']);
        $this->assertStringContainsString('not changed unilaterally', $disclosure['term_variation']['notice']);
        $this->assertArrayHasKey('default_penalty_interest_ceiling', $disclosure['default_and_penalties']);
    }

    public function test_valid_offer_acceptance_records_separate_credit_reporting_consent(): void
    {
        [$customer, $operations, $application] = $this->approvedApplication();
        $offer = app(ProductionCreditOfferService::class)->createOffer($application, $operations, [
            'fee_treatment' => 'financed',
            'expires_in_minutes' => 60,
        ]);

        Sanctum::actingAs($customer);
        $shown = $this->getJson("/api/credit/offers/{$offer->id}")->assertOk();
        $hash = (string) $shown->json('data.disclosure_hash');

        $this->postJson("/api/credit/offers/{$offer->id}/accept", [
            'accept_disclosures' => true,
            'disclosure_hash' => $hash,
        ])->assertOk();

        $this->assertDatabaseHas('consent_records', [
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_REPORTING,
            'policy_version' => 'umra-credit-reporting-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
        ]);

        $consent = ConsentRecord::query()
            ->where('user_id', $customer->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_REPORTING)
            ->latest()
            ->firstOrFail();

        $this->assertSame($offer->offer_reference, $consent->metadata['offer_reference']);
        $this->assertSame($hash, $consent->metadata['disclosure_hash']);
        $this->assertTrue($consent->metadata['notice_in_disclosure']);
    }

    public function test_credit_reference_submission_requires_consent_and_preserves_positive_and_negative_evidence(): void
    {
        [$customer, , $loan] = $this->productionLoan();

        config([
            'services.crb.reporting_url' => 'https://crb.example.test/credit-information',
            'services.crb.reporting_token' => 'test-token',
        ]);

        Http::fake([
            'https://crb.example.test/credit-information' => Http::response([
                'reference' => 'crb-outbound-001',
            ], 200),
        ]);

        $service = app(CreditReferenceReportingService::class);
        $submission = $service->stageLoan($loan, 'loan_disbursed', 'provider-disb-1');

        $this->assertSame('positive', $submission->information_type);
        $this->assertSame(64, strlen($submission->payload_hash));

        try {
            $service->submit($submission);
            $this->fail('Expected outbound credit reporting to fail without credit-reporting consent.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('consent', strtolower($exception->getMessage()));
        }

        ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_REPORTING,
            'policy_version' => 'test-reporting-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'test',
            'granted_at' => now(),
        ]);

        $submitted = $service->submit($submission->fresh());
        $this->assertSame(CreditReferenceSubmission::STATUS_SUBMITTED, $submitted->status);
        $this->assertSame('crb-outbound-001', $submitted->provider_reference);

        $this->app['db']->table('credit_repayment_schedule_items')
            ->where('loan_id', $loan->id)
            ->update(['due_date' => today()->subDays(3)]);

        $negative = $service->stageLoan($loan->fresh(), 'non_performing', 'npl-day-1');
        $this->assertSame('negative', $negative->information_type);
        $this->assertGreaterThan(0, data_get($negative->payload, 'credit.days_past_due'));
    }

    public function test_complaints_receive_thirty_day_sla_and_cannot_close_without_resolution_summary(): void
    {
        $institution = $this->institution('Complaint');
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);

        Sanctum::actingAs($customer);
        $response = $this->postJson('/api/support-cases', [
            'category' => 'loan_complaint',
            'subject' => 'Loan disclosure question',
            'description' => 'Please explain the fee shown on my loan.',
        ])->assertCreated();

        $caseId = (int) $response->json('data.case.id');
        $case = SupportCase::query()->findOrFail($caseId);
        $this->assertSame('umra_consumer_complaint', $case->regulatory_category);
        $this->assertGreaterThanOrEqual(29, now()->diffInDays($case->sla_due_at));
        $this->assertLessThanOrEqual(30, now()->diffInDays($case->sla_due_at));

        Sanctum::actingAs($operations);
        $this->patchJson("/api/admin/support-cases/{$caseId}", [
            'status' => SupportCase::STATUS_RESOLVED,
        ])->assertUnprocessable();

        $this->patchJson("/api/admin/support-cases/{$caseId}", [
            'status' => SupportCase::STATUS_RESOLVED,
            'resolution_summary' => 'The fee calculation and timing were explained to the customer.',
        ])->assertOk();

        $this->assertDatabaseHas('support_cases', [
            'id' => $caseId,
            'status' => SupportCase::STATUS_RESOLVED,
            'resolution_summary' => 'The fee calculation and timing were explained to the customer.',
        ]);
    }

    public function test_guarantor_contacts_are_explicit_and_capped_at_two(): void
    {
        [$customer, , $application] = $this->approvedApplication(guarantorsRequired: 2);
        Sanctum::actingAs($customer);

        foreach (['256700991001', '256700991002'] as $phone) {
            $this->postJson("/api/credit/applications/{$application->id}/guarantors/request-code", [
                'phone' => $phone,
                'name' => 'Guarantor',
            ])->assertOk();
        }

        $this->postJson("/api/credit/applications/{$application->id}/guarantors/request-code", [
            'phone' => '256700991003',
            'name' => 'Third Guarantor',
        ])->assertStatus(409);

        $this->assertDatabaseCount('loan_guarantors', 2);
        $this->assertDatabaseHas('loan_guarantors', [
            'loan_application_id' => $application->id,
            'phone' => '256700991001',
            'verification_method' => 'otp_pending',
        ]);

        $message = $this->app['db']->table('sms_messages')
            ->where('to', '256700991001')
            ->value('message');

        $this->assertStringContainsString('If you agree', (string) $message);
        $this->assertStringContainsString('loan application', (string) $message);
    }

    public function test_npl_policy_freezes_principal_and_enforces_default_and_recovery_caps(): void
    {
        [, , $loan] = $this->productionLoan();

        $this->app['db']->table('credit_repayment_schedule_items')
            ->where('loan_id', $loan->id)
            ->update(['due_date' => today()->subDay()]);

        $policy = app(NplRecoveryPolicyService::class);
        $control = $policy->evaluate($loan->fresh());

        $this->assertNotNull($control->non_performing_at);
        $this->assertSame(150000, $control->principal_at_npl_minor);
        $this->assertSame(7500, $control->default_penalty_cap_minor);
        $this->assertSame(150000, $control->recoverable_interest_cap_minor);
        $this->assertSame(300000, $control->total_recoverable_cap_minor);
        $this->assertSame('enforce', $control->enforcement_mode);

        $policy->recordPenaltyAccrual($loan, 7500);
        $this->expectException(\InvalidArgumentException::class);
        $policy->recordPenaltyAccrual($loan, 1);
    }

    public function test_interest_rate_variation_requires_prior_umra_approval_and_customer_consent(): void
    {
        [$customer, $operations, $loan] = $this->productionLoan();
        $service = app(CreditTermVariationService::class);

        $variation = $service->propose($loan, $operations, [
            'interest_rate_percent' => 9.5,
        ], 'Proposed rate amendment');

        $this->assertTrue($variation->requires_umra_approval);

        try {
            $service->customerConsent($variation, $customer, ['channel' => 'test']);
            $this->fail('Expected prior UMRA approval to be required.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('prior UMRA approval', $exception->getMessage());
        }

        $approved = $service->recordUmraApproval(
            $variation,
            'UMRA-APPROVAL-2026-001',
            str_repeat('a', 64),
        );
        $consented = $service->customerConsent($approved, $customer, ['channel' => 'test']);

        $this->assertNotNull($consented->customer_consented_at);
        $this->assertSame(64, strlen((string) $consented->customer_consent_hash));

        Sanctum::actingAs($operations);
        $this->postJson("/api/admin/umra/term-variations/{$consented->id}/apply")
            ->assertStatus(409);

        $this->assertDatabaseMissing('credit_term_variations', [
            'id' => $consented->id,
            'status' => CreditTermVariation::STATUS_APPLIED,
        ]);
    }

    public function test_successful_provider_transaction_issues_one_immutable_e_receipt(): void
    {
        [$customer, , $loan] = $this->productionLoan();

        $receipt = TransactionReceipt::query()
            ->where('loan_id', $loan->id)
            ->where('transaction_type', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)
            ->firstOrFail();

        $this->assertSame('issued', $receipt->status);
        $this->assertSame(64, strlen($receipt->payload_hash));
        $this->assertNull($receipt->delivered_at);

        Sanctum::actingAs($customer);
        $this->getJson('/api/receipts')
            ->assertOk()
            ->assertJsonPath('data.receipts.0.receipt_reference', $receipt->receipt_reference);

        $this->getJson("/api/receipts/{$receipt->id}")
            ->assertOk()
            ->assertJsonPath('data.receipt.payload_hash', $receipt->payload_hash);
    }

    public function test_admin_can_generate_and_export_umra_books_and_records(): void
    {
        $institution = $this->institution('UMRA report');
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);

        Sanctum::actingAs($operations);
        $response = $this->postJson('/api/admin/governance/regulatory-reports', [
            'report_type' => 'umra_books_and_records',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.report.regulator', 'UMRA')
            ->assertJsonPath('data.report.status', 'validated');

        $reportId = (int) $response->json('data.report.id');
        $hash = (string) $response->json('data.report.payload_hash');

        $export = $this->get("/api/admin/governance/regulatory-reports/{$reportId}/export?format=json");
        $export->assertOk();
        $export->assertHeader('X-OpFin-Evidence-Hash', $hash);
        $export->assertHeader('Content-Type', 'application/json');

        $this->assertStringContainsString('umra_books_and_records', $export->getContent());
        $this->assertStringContainsString('books_available_for_inspection', $export->getContent());
    }

    private function approvedApplication(int $duration = 30, int $guarantorsRequired = 0): array
    {
        $institution = $this->institution('Approved');
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => fake()->unique()->numerify('25670#######'),
            'national_id' => fake()->unique()->bothify('CM############'),
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        $product = LoanProduct::create([
            'name' => 'UMRA Controlled Credit',
            'type' => 'Cash',
            'institution_id' => $institution->id,
            'status' => 'Active',
        ]);
        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => $duration,
            'guarantors_required' => $guarantorsRequired,
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
            'distribution_channel' => 'web',
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
            'policy_version' => 'umra-test-v1',
            'reason_codes' => ['AFFORDABILITY_REVIEWED'],
            'decision_summary' => 'Approved for UMRA controls test.',
            'decided_at' => now(),
        ]);

        return [$customer, $operations, $application];
    }

    private function productionLoan(): array
    {
        [$customer, $operations, $application] = $this->approvedApplication();
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
            'provider_reference' => 'umra-test-payout-'.$offer->id,
        ]);

        $loan = $offers->syncDisbursementState($payout->fresh());
        $this->assertInstanceOf(Loan::class, $loan);

        return [$customer, $operations, $loan];
    }

    private function institution(string $suffix): Institution
    {
        return Institution::create([
            'name' => $suffix.' Institution',
            'address' => 'Kampala',
            'phone' => fake()->unique()->numerify('25671#######'),
            'email' => fake()->unique()->safeEmail(),
        ]);
    }
}
