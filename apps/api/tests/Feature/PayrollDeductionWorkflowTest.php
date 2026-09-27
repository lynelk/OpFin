<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\FinancialIntent;
use App\Models\FinancialProduct;
use App\Models\FinancingApplication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollDeductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_government_payroll_deduction_moves_from_affordability_to_reconciliation(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $application = $this->financingApplication($customer);
        $consent = ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'payroll-undertaking-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'app',
            'granted_at' => now(),
            'metadata' => ['scope' => 'government_payroll_deduction'],
        ]);

        Sanctum::actingAs($customer);
        $case = $this->postJson('/api/payroll-deduction/cases', [
            'financing_application_id' => $application->id,
            'vote_code' => 'VOTE-001',
            'vote_name' => 'Test Government Vote',
            'employment_reference' => 'EMP-PRIVATE-001',
        ], $this->headers('create-1'))
            ->assertCreated()
            ->assertJsonPath('data.case.status', 'affordability_pending')
            ->json('data.case');

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/affordability', [
            'affordable' => true,
            'affordable_amount_minor' => 5000000,
            'provider_reference' => 'PDMS-AFF-1',
        ], $this->headers('aff-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'affordable');

        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'requested_deduction_minor' => 350000,
            'undertaking_consent_record_id' => $consent->id,
            'provider_agreement_reference' => 'AGR-001',
        ], $this->headers('reserve-request-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reservation_pending');

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'reserved' => true,
            'reservation_reference' => 'PDMS-RES-1',
            'provider_agreement_reference' => 'AGR-001',
        ], $this->headers('reserve-confirm-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reserved');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/key-facts', [
            'key_facts' => [
                'version' => 'KFD-v1',
                'principal_minor' => 3000000,
                'deduction_minor' => 350000,
                'tenure_months' => 12,
            ],
            'provider_reference' => 'PDMS-KFD-1',
        ], $this->headers('key-facts-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'vote_approval_pending');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/vote-decision', [
            'approved' => true,
            'provider_reference' => 'PDMS-VOTE-1',
        ], $this->headers('vote-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'deduction_approved');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/payroll-submission', [
            'payroll_period' => '2026-10',
            'submission_file_reference' => 'FILE-482-2026-10',
            'submission_code' => '482',
        ], $this->headers('submission-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'payroll_submitted');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/payroll-result', [
            'payroll_period' => '2026-10',
            'result_category' => 'success',
            'recovered_minor' => 350000,
            'provider_reference' => 'PDMS-RESULT-1',
        ], $this->headers('result-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reconciliation_pending');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reconcile', [
            'payroll_period' => '2026-10',
            'recovered_minor' => 350000,
            'provider_reference' => 'BANK-SETTLEMENT-1',
        ], $this->headers('reconcile-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reconciled')
            ->assertJsonPath('data.case.reconciliations.0.variance_minor', 0);
    }

    public function test_rejected_payroll_result_enters_amendment_loop(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);

        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-result', [
            'payroll_period' => '2026-10',
            'result_category' => 'off_payroll_lt_3_months',
            'recovered_minor' => 0,
            'rejection_reason' => 'Officer temporarily off payroll.',
        ], $this->headers('off-payroll-1'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'amendment_required')
            ->assertJsonPath('data.case.reconciliations.0.result_category', 'off_payroll_lt_3_months');
    }

    public function test_reconciliation_cannot_be_forced_by_operator_expected_amount(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);

        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-result', [
            'payroll_period' => '2026-10',
            'result_category' => 'success',
            'recovered_minor' => 340000,
            'provider_reference' => 'PDMS-RESULT-MISMATCH',
        ], $this->headers('mismatch-result'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reconciliation_pending');

        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/reconcile', [
            'payroll_period' => '2026-10',
            'expected_minor' => 340000,
            'recovered_minor' => 340000,
            'provider_reference' => 'BANK-SETTLEMENT-MISMATCH',
        ], $this->headers('mismatch-reconcile'))
            ->assertOk()
            ->assertJsonPath('data.case.status', 'reconciliation_exception')
            ->assertJsonPath('data.case.reconciliations.0.expected_minor', 350000)
            ->assertJsonPath('data.case.reconciliations.0.variance_minor', -10000);
    }

    public function test_payroll_event_evidence_is_database_immutable(): void
    {
        [$caseId] = $this->caseAtPayrollSubmission();
        $eventId = DB::table('payroll_deduction_events')
            ->where('payroll_deduction_case_id', $caseId)
            ->value('id');

        $this->expectException(QueryException::class);

        DB::table('payroll_deduction_events')
            ->where('id', $eventId)
            ->update(['event_type' => 'tampered']);
    }

    public function test_state_changes_require_idempotency_key(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        Sanctum::actingAs($customer);

        $this->postJson('/api/payroll-deduction/cases', [
            'financing_application_id' => $application->id,
        ])->assertUnprocessable();
    }

    public function test_provider_capability_is_fail_closed_until_machine_interface_is_configured(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/payroll-deduction/provider-capability')
            ->assertOk()
            ->assertJsonPath('data.provider.provider', 'pdms')
            ->assertJsonPath('data.provider.machine_interface_active', false)
            ->assertJsonPath('data.provider.mode', 'manual_evidence');
    }

    private function financingApplication(User $user): FinancingApplication
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

        $product = FinancialProduct::create([
            'reference' => (string) Str::uuid(),
            'code' => 'SALARY-PAYROLL',
            'version' => 1,
            'name' => 'Salary-linked finance',
            'rail' => 'CONVENTIONAL',
            'family' => 'salary_finance',
            'contract_type' => 'CREDIT',
            'jurisdiction' => 'UG',
            'currency' => 'UGX',
            'status' => 'live',
        ]);
        $intent = FinancialIntent::create([
            'reference' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'need_type' => 'salary_finance',
            'principles_preference' => 'CONVENTIONAL_ONLY',
            'amount_minor' => 3000000,
            'currency' => 'UGX',
            'status' => 'open',
        ]);

        return FinancingApplication::create([
            'reference' => (string) Str::uuid(),
            'financial_intent_id' => $intent->id,
            'financial_product_id' => $product->id,
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'status' => 'submitted',
        ]);
    }

    private function caseAtPayrollSubmission(): array
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $application = $this->financingApplication($customer);
        $consent = ConsentRecord::create([
            'user_id' => $customer->id,
            'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'payroll-undertaking-v1',
            'status' => ConsentRecord::STATUS_GRANTED,
            'channel' => 'app',
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $caseId = $this->postJson('/api/payroll-deduction/cases', [
            'financing_application_id' => $application->id,
        ], $this->headers('setup-create'))->json('data.case.id');

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/affordability', [
            'affordable' => true,
            'affordable_amount_minor' => 500000,
        ], $this->headers('setup-aff'));

        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$caseId.'/reservation', [
            'requested_deduction_minor' => 350000,
            'undertaking_consent_record_id' => $consent->id,
            'provider_agreement_reference' => 'SETUP-AGR',
        ], $this->headers('setup-reserve-request'));

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/reservation', [
            'reserved' => true,
            'reservation_reference' => 'SETUP-RES',
            'provider_agreement_reference' => 'SETUP-AGR',
        ], $this->headers('setup-reserve'));
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/key-facts', [
            'key_facts' => ['version' => 'v1'],
        ], $this->headers('setup-keyfacts'));
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/vote-decision', [
            'approved' => true,
        ], $this->headers('setup-vote'));
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-submission', [
            'payroll_period' => '2026-10',
        ], $this->headers('setup-submission'));

        return [$caseId, $operations];
    }

    private function headers(string $key): array
    {
        return [
            'Idempotency-Key' => $key,
            'X-Correlation-ID' => (string) Str::uuid(),
        ];
    }
}
