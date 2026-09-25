<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\CreditDecision;
use App\Models\CreditOffer;
use App\Models\CreditRepaymentScheduleItem;
use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\CreditReferenceReportingService;
use App\Services\CreditTermGovernanceService;
use App\Services\RegulatoryReportingService;
use App\Services\TransactionReceiptService;
use App\Services\UmraNplCapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UmraDigitalLendingControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPricingPolicy();
    }

    public function test_customer_complaint_gets_thirty_day_regulatory_clock_and_procedure_snapshot(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/support-cases', [
            'category' => 'complaint',
            'subject' => 'Loan fee question',
            'description' => 'Please explain this charge.',
        ])->assertCreated()
            ->assertJsonPath('data.complaints_procedure.resolution_target_days', 30);

        $case = DB::table('support_cases')->where('id', $response->json('data.support_case.id'))->first();
        $this->assertNotNull($case->regulatory_due_at);
        $this->assertSame(
            30,
            (int) now()->startOfDay()->diffInDays(Carbon::parse($case->regulatory_due_at)->startOfDay()),
        );
    }

    public function test_guarantor_contacts_are_limited_to_two_and_require_electronic_confirmation(): void
    {
        Queue::fake();
        [$customer, , $application] = $this->facility();
        Sanctum::actingAs($customer);

        foreach ([
            ['name' => 'One Contact', 'phone' => '256700001001'],
            ['name' => 'Two Contact', 'phone' => '256700001002'],
        ] as $guarantor) {
            $this->postJson("/api/credit/applications/{$application->id}/guarantors", $guarantor)
                ->assertCreated()
                ->assertJsonPath('data.guarantor.status', 'pending');
        }

        $this->postJson("/api/credit/applications/{$application->id}/guarantors", [
            'name' => 'Third Contact',
            'phone' => '256700001003',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('guarantor_contacts', 2);
        $this->assertSame(2, DB::table('guarantor_contacts')->where('status', 'pending')->count());
    }

    public function test_npl_default_interest_cap_is_tracked_and_enforced(): void
    {
        config(['opfin.regulatory.licence_class' => 'TEST-LEGACY-CLASS']);
        DB::table('financial_policies')->where('policy_type', 'regulatory_pricing')->update(['licence_class' => 'TEST-LEGACY-CLASS']);
        [, , , $offer, $loan] = $this->facility(withLoan: true);

        CreditRepaymentScheduleItem::create([
            'loan_id' => $loan->id,
            'credit_offer_id' => $offer->id,
            'installment_number' => 1,
            'due_date' => now()->subDay()->toDateString(),
            'principal_minor' => 100000,
            'interest_minor' => 20000,
            'fees_minor' => 0,
            'total_due_minor' => 120000,
            'principal_outstanding_minor' => 100000,
            'interest_outstanding_minor' => 20000,
            'fees_outstanding_minor' => 0,
            'total_outstanding_minor' => 120000,
            'status' => CreditRepaymentScheduleItem::STATUS_DUE,
        ]);

        $service = app(UmraNplCapService::class);
        $evaluated = $service->evaluate($loan);

        $this->assertSame('Non-Performing', $evaluated->status);
        $this->assertSame(10000, $evaluated->default_interest_cap_minor);
        $this->assertSame(100000, $evaluated->principal_at_npl_minor);

        $accrued = $service->accrueDefaultInterest($evaluated, now()->addDays(150));
        $this->assertSame(10000, $accrued->default_interest_accrued_minor);

        $capped = $service->accrueDefaultInterest($accrued, now()->addDays(180));
        $this->assertSame(10000, $capped->default_interest_accrued_minor);
        $this->assertSame(10000, $capped->default_interest_cap_minor);
    }

    public function test_interest_rate_change_requires_prior_umra_approval_and_maker_checker(): void
    {
        [, $operations, $application] = $this->facility();
        $application->institution->update(['regulator_code' => 'UMRA']);
        $term = $application->loanProductTerm;
        $otherOfficer = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'institution_id' => $operations->institution_id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $term->update(['interest_rate' => 11]);
    }

    public function test_governed_interest_rate_change_can_be_applied_with_umra_evidence(): void
    {
        [, $operations, $application] = $this->facility();
        $application->institution->update(['regulator_code' => 'UMRA']);
        $term = $application->loanProductTerm;
        $checker = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'institution_id' => $operations->institution_id,
        ]);
        $service = app(CreditTermGovernanceService::class);

        $change = $service->requestChange($term, $operations, ['interest_rate' => 11], 'Approved product repricing');
        $approved = $service->approve($change->id, $checker, 'UMRA-RATE-APPROVAL-001', now()->toDateString());
        $this->assertSame('approved', $approved->status);

        $updated = $service->apply($change->id, $checker);
        $this->assertSame(11.0, (float) $updated->interest_rate);
        $this->assertSame('UMRA-RATE-APPROVAL-001', $updated->umra_interest_approval_reference);
        $this->assertDatabaseHas('credit_term_change_requests', ['id' => $change->id, 'status' => 'applied']);
    }

    public function test_receipt_is_idempotent_and_instant_acknowledgement_is_queued(): void
    {
        Queue::fake();
        [$customer, $operations, $application, $offer, $loan] = $this->facility(withLoan: true);

        $money = MobileMoneyTransaction::create([
            'loan_id' => $loan->id,
            'credit_offer_id' => $offer->id,
            'user_id' => $customer->id,
            'institution_id' => $operations->institution_id,
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 25000,
            'currency' => 'UGX',
            'phone' => $customer->phone,
            'idempotency_key' => 'receipt-test-1',
            'internal_reference' => 'RCP-TEST-1',
            'provider_reference' => 'provider-rcp-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
        ]);

        $service = app(TransactionReceiptService::class);
        $one = $service->issue($money, 'loan_repayment');
        $two = $service->issue($money, 'loan_repayment');

        $this->assertSame($one->id, $two->id);
        $this->assertDatabaseCount('transaction_receipts', 1);
        $this->assertSame(64, strlen($one->receipt_hash));
    }

    public function test_credit_reporting_is_blocked_without_specific_consent_and_submits_with_consent(): void
    {
        [$customer, , , , $loan] = $this->facility(withLoan: true);
        $customer->forceFill([
            'first_name' => 'Amina',
            'last_name' => 'Kato',
            'national_id' => 'CM123456789012',
            'nin_status' => 'VALID',
        ])->save();

        $service = app(CreditReferenceReportingService::class);
        $record = $service->queueLoanEvent($loan->fresh(), 'origination');

        config()->set('services.credit_reference_reporting.url', 'https://credit.example.test/report');
        config()->set('services.credit_reference_reporting.token', 'test-token');
        Http::fake(['https://credit.example.test/report' => Http::response(['reference' => 'CR-001'], 200)]);

        $service->submitPending();
        $this->assertDatabaseHas('credit_information_reports', [
            'id' => $record->id,
            'status' => 'blocked_consent',
        ]);
        Http::assertNothingSent();

        ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_INFORMATION_REPORTING,
            'policy_version' => 'credit-reporting-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'test',
            'granted_at' => now(),
        ]);

        $service->submitPending();
        $this->assertDatabaseHas('credit_information_reports', [
            'id' => $record->id,
            'status' => 'submitted',
            'provider_reference' => 'CR-001',
        ]);
        Http::assertSentCount(1);
    }

    public function test_admin_can_generate_umra_books_and_records_evidence_pack(): void
    {
        $report = app(RegulatoryReportingService::class)->generate(
            'umra_books_and_records',
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $this->assertSame('UMRA', $report->regulator);
        $this->assertSame('financially_reconciled', $report->status);
        $payload = json_decode($report->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('loan_register', $payload);
        $this->assertArrayHasKey('payment_register', $payload);
        $this->assertArrayHasKey('ledger_register', $payload);
    }

    private function installPricingPolicy(): void
    {
        DB::table('financial_policies')->insert([
            'code' => 'umra-controls-regulatory-pricing',
            'policy_type' => 'regulatory_pricing',
            'jurisdiction_country' => 'UG',
            'licence_class' => null,
            'product_scope' => null,
            'version' => 1,
            'status' => 'active',
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
            'rules' => json_encode([
                'interest_basis' => 'original_principal',
                'cycle_days' => ['daily' => 1, 'weekly' => 7, 'monthly' => 30],
                'repayment_frequency_days' => ['daily' => 1, 'weekly' => 7, 'fortnightly' => 14, 'monthly' => 30],
                'default_interest' => [
                    'basis' => 'outstanding_principal',
                    'rate_cycle' => 'monthly',
                    'max_rate_percent' => 2,
                    'cap_percent_of_initial_interest' => 50,
                    'recovery_cap_percent_of_principal_at_npl' => 100,
                ],
            ], JSON_THROW_ON_ERROR),
            'source_reference' => 'umra-controls-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function facility(bool $withLoan = false): array
    {
        $institution = Institution::create([
            'name' => 'UMRA Controls Institution',
            'address' => 'Kampala',
            'phone' => '256700000777',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'institution_id' => $institution->id,
            'phone' => fake()->unique()->numerify('25670#######'),
        ]);
        $operations = User::factory()->create([
            'role' => User::ROLE_OPERATIONS,
            'institution_id' => $institution->id,
        ]);
        $product = LoanProduct::create([
            'name' => 'UMRA Controlled Credit',
            'type' => 'Cash',
            'institution_id' => $institution->id,
        ]);
        $term = LoanProductTerm::create([
            'loan_product_id' => $product->id,
            'interest_rate' => 10,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Monthly',
            'repayment_frequency' => 'Monthly',
            'duration' => 90,
            'status' => 'Active',
            'default_interest_rate' => 2,
            'default_interest_cycle' => 'Monthly',
        ]);
        $application = LoanApplication::create([
            'user_id' => $customer->id,
            'loan_product_id' => $product->id,
            'loan_product_term_id' => $term->id,
            'institution_id' => $institution->id,
            'amount' => 100000,
            'status' => 'Approved',
            'reason' => 'Household need',
            'approved_at' => now(),
        ]);
        $decision = CreditDecision::create([
            'loan_application_id' => $application->id,
            'user_id' => $customer->id,
            'status' => CreditDecision::STATUS_APPROVED,
            'requested_amount_minor' => 100000,
            'approved_amount_minor' => 100000,
            'monthly_income_minor' => 500000,
            'estimated_obligation_minor' => 100000,
            'policy_version' => 'test-umra-v1',
            'reason_codes' => ['TEST'],
            'decision_summary' => 'Test approval.',
            'decided_by' => $operations->id,
            'decided_at' => now(),
        ]);
        $offer = CreditOffer::create([
            'loan_application_id' => $application->id,
            'credit_decision_id' => $decision->id,
            'user_id' => $customer->id,
            'institution_id' => $institution->id,
            'created_by' => $operations->id,
            'offer_reference' => 'TEST-OFFER-'.fake()->unique()->numerify('######'),
            'version' => 1,
            'status' => CreditOffer::STATUS_DISBURSED,
            'currency' => 'UGX',
            'principal_amount_minor' => 100000,
            'interest_amount_minor' => 20000,
            'fees_minor' => 0,
            'net_disbursement_minor' => 100000,
            'total_repayment_minor' => 120000,
            'duration_days' => 90,
            'interest_rate_percent' => 10,
            'interest_cycle' => 'Monthly',
            'interest_type' => 'Flat',
            'repayment_frequency' => 'Monthly',
            'fee_treatment' => 'financed',
            'policy_version' => 'test-umra-v1',
            'pricing_snapshot' => [],
            'disclosure_snapshot' => [],
            'offered_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
            'accepted_at' => now()->subDay(),
        ]);

        $loan = null;
        if ($withLoan) {
            $loan = Loan::withoutEvents(function () use ($application, $offer) {
                $loan = new Loan;
                $loan->forceFill([
                    'user_id' => $application->user_id,
                    'loan_product_id' => $application->loan_product_id,
                    'loan_product_term_id' => $application->loan_product_term_id,
                    'institution_id' => $application->institution_id,
                    'loan_application_id' => $application->id,
                    'credit_offer_id' => $offer->id,
                    'amount' => 100000,
                    'status' => 'Active',
                    'reason' => $application->reason,
                    'disbursed_at' => now()->subMonths(3),
                    'duration' => 90,
                    'repayment_amount' => 120000,
                    'repayment_start_date' => now()->subMonths(2)->toDateString(),
                    'umra_npl_cap_enforcement_enabled' => true,
                    'initial_interest_minor' => 20000,
                    'default_interest_cap_minor' => 10000,
                ]);
                $loan->save();

                return $loan;
            });
        }

        return [$customer, $operations, $application, $offer, $loan];
    }
}
