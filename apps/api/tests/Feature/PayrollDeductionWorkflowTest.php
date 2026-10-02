<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\FinancialIntent;
use App\Models\FinancialProduct;
use App\Models\FinancingApplication;
use App\Models\FinancingArrangement;
use App\Models\User;
use App\Services\PayrollDeduction\PayrollDeductionService;
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

        $consent->update(['metadata' => ['scope' => 'government_payroll_deduction', 'payroll_case_id' => $case['id'], 'payroll_case_reference' => $case['reference'], 'requested_deduction_minor' => 350000]]);
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

    private function affordableCase(): array
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $operations = User::factory()->create(['role' => User::ROLE_OPERATIONS]);
        $application = $this->financingApplication($customer);
        Sanctum::actingAs($customer);
        $case = $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('boundary-create'))
            ->assertCreated()->json('data.case');
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/affordability', [
            'affordable' => true, 'affordable_amount_minor' => 500000,
        ], $this->headers('boundary-affordability'))->assertOk();

        return [$case, $customer, $operations, $application];
    }

    private function reserveWithAtomicUndertaking(array $case, User $customer): void
    {
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/undertaking', [
            'authorised' => true, 'requested_deduction_minor' => 350000,
        ], $this->headers('atomic-undertaking'))->assertOk()->assertJsonPath('data.case.status', 'reservation_pending');
    }

    public function test_atomic_undertaking_is_case_bound_replay_safe_and_does_not_revoke_generic_credit_consent(): void
    {
        [$case, $customer] = $this->affordableCase();
        $generic = ConsentRecord::create(['user_id' => $customer->id, 'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'generic-credit-v1', 'status' => 'granted', 'channel' => 'app', 'granted_at' => now()]);
        $this->reserveWithAtomicUndertaking($case, $customer);
        $this->reserveWithAtomicUndertaking($case, $customer);
        $this->assertSame(1, ConsentRecord::where('purpose', 'payroll_deduction')->count());
        $this->assertSame('granted', $generic->fresh()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'payroll.undertaking.granted')->count());
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/undertaking', [
            'authorised' => true, 'requested_deduction_minor' => 360000,
        ], $this->headers('atomic-undertaking'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'requested_deduction_minor' => 350000]);
    }

    public function test_generic_credit_consent_cannot_authorise_payroll_deduction(): void
    {
        [$case, $customer] = $this->affordableCase();
        $generic = ConsentRecord::create(['user_id' => $customer->id, 'purpose' => ConsentRecord::PURPOSE_CREDIT_PROCESSING,
            'policy_version' => 'generic-credit-v1', 'status' => 'granted', 'channel' => 'app', 'granted_at' => now()]);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'requested_deduction_minor' => 350000, 'undertaking_consent_record_id' => $generic->id,
        ], $this->headers('generic-consent'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'affordable']);
    }

    public function test_payroll_replay_key_is_bound_to_command_and_actor(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$case['id'].'/cancel', [], $this->headers('boundary-affordability'))->assertUnprocessable();
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/affordability', [
            'affordable' => true, 'affordable_amount_minor' => 500001,
        ], $this->headers('boundary-affordability'))->assertUnprocessable();
    }

    public function test_invalid_correlation_and_wrong_salary_product_are_rejected_without_creating_cases(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], [
            'Idempotency-Key' => 'bad-correlation', 'X-Correlation-ID' => 'payroll-run-not-a-uuid',
        ])->assertUnprocessable();
        $application->product->update(['family' => 'asset_finance']);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('wrong-family'))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_deduction_cases', 0);
    }

    public function test_removed_space_authority_cannot_start_payroll_case(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $application = $this->financingApplication($customer);
        DB::table('financial_space_memberships')->where('user_id', $customer->id)->update(['deleted_at' => now()]);
        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases', ['financing_application_id' => $application->id], $this->headers('removed-member'))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_deduction_cases', 0);
    }

    public function test_revoked_undertaking_blocks_positive_reservation_confirmation(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        $this->reserveWithAtomicUndertaking($case, $customer);
        ConsentRecord::where('user_id', $customer->id)->where('purpose', 'payroll_deduction')->update(['status' => 'revoked', 'revoked_at' => now()]);
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/reservation', [
            'reserved' => true, 'reservation_reference' => 'REAL-EVIDENCE-REFERENCE', 'provider_agreement_reference' => 'AGREEMENT-REFERENCE',
        ], $this->headers('revoked-reservation'))->assertUnprocessable();
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'reservation_pending']);
    }

    public function test_cancellation_and_expiry_require_evidenced_provider_release_and_preserve_servicing_access(): void
    {
        [$case, $customer, $operations] = $this->affordableCase();
        $this->reserveWithAtomicUndertaking($case, $customer);
        DB::table('payroll_deduction_cases')->where('id', $case['id'])->update(['reservation_expires_at' => now()->subMinute()]);
        $this->assertSame(1, app(PayrollDeductionService::class)->expireReservations());
        $this->assertSame(0, app(PayrollDeductionService::class)->expireReservations());
        $this->assertDatabaseHas('payroll_deduction_cases', ['id' => $case['id'], 'status' => 'cancellation_pending']);
        Sanctum::actingAs($customer);
        $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])
            ->assertStatus(409)->assertJsonPath('data.deletion_status', 'blocked_obligations');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('support_cases', ['customer_id' => $customer->id, 'category' => 'account_deletion']);
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/cancellation-release', [
            'released' => true,
        ], $this->headers('release-without-evidence'))->assertUnprocessable();
        $this->postJson('/api/operations/payroll-deduction/cases/'.$case['id'].'/cancellation-release', [
            'released' => true, 'release_reference' => 'TEST-RELEASE-REFERENCE',
        ], $this->headers('release-with-evidence'))->assertOk()->assertJsonPath('data.case.status', 'cancelled');
    }

    public function test_amended_same_month_retains_original_payroll_result_attempt(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);
        $prefix = '/api/operations/payroll-deduction/cases/'.$caseId;
        $this->postJson($prefix.'/payroll-result', ['payroll_period' => '2026-10', 'result_category' => 'rejected', 'provider_reference' => 'FIRST-REJECTION'], $this->headers('first-result'))->assertOk();
        $this->postJson($prefix.'/amend', [], $this->headers('retry-reservation'))->assertOk()->assertJsonPath('data.case.status', 'reservation_pending');
        $this->postJson($prefix.'/key-facts', ['key_facts' => ['version' => 'v2']], $this->headers('premature-key-facts'))->assertUnprocessable();
        $this->postJson($prefix.'/reservation', ['reserved' => true, 'reservation_reference' => 'SECOND-RESERVATION', 'provider_agreement_reference' => 'SECOND-AGREEMENT'], $this->headers('second-reservation'))->assertOk();
        $this->postJson($prefix.'/key-facts', ['key_facts' => ['version' => 'v2']], $this->headers('second-facts'))->assertOk();
        $this->postJson($prefix.'/vote-decision', ['approved' => true], $this->headers('second-vote'))->assertOk();
        $this->postJson($prefix.'/payroll-submission', ['payroll_period' => '2026-10'], $this->headers('second-submission'))->assertOk();
        $this->postJson($prefix.'/payroll-result', ['payroll_period' => '2026-10', 'result_category' => 'success', 'provider_reference' => 'SECOND-RESULT', 'recovered_minor' => 350000], $this->headers('second-result'))->assertOk();
        $this->assertDatabaseCount('payroll_deduction_reconciliations', 2);
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 1, 'result_category' => 'rejected', 'provider_reference' => 'FIRST-REJECTION']);
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 2, 'result_category' => 'success']);
        $this->postJson($prefix.'/reconcile', ['payroll_period' => '2026-10', 'recovered_minor' => 350000], $this->headers('second-settlement'))->assertOk();
        $this->assertDatabaseHas('payroll_deduction_reconciliations', ['submission_attempt' => 1, 'status' => 'rejected']);
    }

    public function test_payroll_original_result_cannot_be_changed_through_raw_sql(): void
    {
        [$caseId, $operations] = $this->caseAtPayrollSubmission();
        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-result', [
            'payroll_period' => '2026-10', 'result_category' => 'success', 'recovered_minor' => 340000,
        ], $this->headers('immutable-result'))->assertOk();
        $this->expectException(QueryException::class);
        DB::table('payroll_deduction_reconciliations')->where('payroll_deduction_case_id', $caseId)->update(['expected_minor' => 340000]);
    }

    public function test_canonical_financing_arrangement_blocks_deletion_without_legacy_loan(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $application = $this->financingApplication($customer);
        FinancingArrangement::create([
            'reference' => (string) Str::uuid(), 'financing_application_id' => $application->id,
            'financial_product_id' => $application->financial_product_id, 'user_id' => $customer->id,
            'financial_space_id' => $application->financial_space_id, 'contract_type' => 'CREDIT',
            'principal_or_cost_minor' => 200000, 'total_obligation_minor' => 230000,
            'currency' => 'UGX', 'status' => 'contracting',
            'contract_snapshot' => ['test_fixture' => true],
            'contract_hash' => hash('sha256', json_encode(['test_fixture' => true], JSON_THROW_ON_ERROR)),
        ]);
        Sanctum::actingAs($customer);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', false)
            ->assertJsonPath('data.active_obligations.0.code', 'financing_arrangement')
            ->assertJsonPath('data.active_obligations.0.amount_basis', 'contract_total_not_current_balance')
            ->assertJsonPath('data.active_obligations.0.provider.direct_contact_available', false);
        $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
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
            'code' => 'SALARY-PAYROLL-'.Str::uuid(),
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
        ], $this->headers('setup-create'))->assertCreated()->json('data.case.id');
        $consent->update(['metadata' => ['scope' => 'government_payroll_deduction', 'payroll_case_id' => $caseId, 'payroll_case_reference' => DB::table('payroll_deduction_cases')->where('id', $caseId)->value('reference'), 'requested_deduction_minor' => 350000]]);

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/affordability', [
            'affordable' => true,
            'affordable_amount_minor' => 500000,
        ], $this->headers('setup-aff'))->assertOk();

        Sanctum::actingAs($customer);
        $this->postJson('/api/payroll-deduction/cases/'.$caseId.'/reservation', [
            'requested_deduction_minor' => 350000,
            'undertaking_consent_record_id' => $consent->id,
            'provider_agreement_reference' => 'SETUP-AGR',
        ], $this->headers('setup-reserve-request'))->assertOk();

        Sanctum::actingAs($operations);
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/reservation', [
            'reserved' => true,
            'reservation_reference' => 'SETUP-RES',
            'provider_agreement_reference' => 'SETUP-AGR',
        ], $this->headers('setup-reserve'))->assertOk();
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/key-facts', [
            'key_facts' => ['version' => 'v1'],
        ], $this->headers('setup-keyfacts'))->assertOk();
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/vote-decision', [
            'approved' => true,
        ], $this->headers('setup-vote'))->assertOk();
        $this->postJson('/api/operations/payroll-deduction/cases/'.$caseId.'/payroll-submission', [
            'payroll_period' => '2026-10',
        ], $this->headers('setup-submission'))->assertOk();

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
