<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeopleCoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function businessWithWorkers(): array
    {
        $owner = User::factory()->create();
        $hr = User::factory()->create();
        $worker = User::factory()->create();
        $payroll = User::factory()->create();
        Sanctum::actingAs($owner);
        $spaceId = $this->postJson('/api/financial-spaces', [
            'type' => 'business', 'name' => 'OpFin Test Employer',
        ])->assertCreated()->json('data.space.id');

        foreach ([$hr->id => 'administrator', $worker->id => 'member', $payroll->id => 'payroll_manager'] as $userId => $role) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $spaceId,
                'user_id' => $userId,
                'role' => $role,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $base = '/api/people/spaces/'.$spaceId;
        $engagement = $this->postJson($base.'/employees', [
            'user_id' => $worker->id,
            'workforce_number' => 'EMP-100',
            'engagement_type' => 'permanent',
            'job_title' => 'Field Coordinator',
            'supervisor_user_id' => $hr->id,
            'starts_on' => '2026-01-01',
        ])->assertCreated()->json('data.employee.id');

        return [$owner, $hr, $worker, $payroll, $spaceId, $engagement, $base];
    }

    public function test_people_employee_visibility_is_tenant_and_finance_isolated(): void
    {
        [$owner, $hr, $worker, $payroll, $spaceId, $engagement, $base] = $this->businessWithWorkers();

        Sanctum::actingAs($worker);
        $this->getJson($base.'/employees')->assertOk()
            ->assertJsonPath('data.employees.data.0.id', $engagement);
        $this->postJson($base.'/employees', [
            'user_id' => $worker->id,
        ])->assertForbidden();
        $this->getJson('/api/financial-spaces/'.$spaceId.'/budgets')->assertForbidden();
        $this->getJson('/api/financial-spaces/'.$spaceId.'/financial-life')->assertForbidden();
        $this->getJson($base.'/payroll/periods')->assertForbidden();
        $this->getJson($base.'/biometrics/connectors')->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($base.'/employees')->assertForbidden();
        $this->getJson($base.'/attendance')->assertForbidden();
    }

    public function test_leave_and_attendance_need_policies_approvals_and_unique_events(): void
    {
        [$owner, $hr, $worker, $payroll, $spaceId, $engagement, $base] = $this->businessWithWorkers();
        $policy = $this->postJson($base.'/leave/policies', [
            'leave_type' => 'annual',
            'effective_from' => '2026-01-01',
            'annual_days' => 21,
        ])->assertCreated()->json('data.policy.id');
        $this->postJson($base.'/leave/policies/'.$policy.'/approve')->assertForbidden();

        Sanctum::actingAs($hr);
        $this->postJson($base.'/leave/policies/'.$policy.'/approve')->assertOk()
            ->assertJsonPath('data.policy.status', 'approved');

        Sanctum::actingAs($worker);
        $leave = $this->postJson($base.'/leave/requests', [
            'engagement_id' => $engagement,
            'leave_type' => 'annual',
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-12',
            'requested_days' => 3,
        ])->assertCreated()->json('data.request.id');
        $this->postJson($base.'/leave/requests/'.$leave.'/decide', ['decision' => 'approved'])
            ->assertForbidden();
        $eventData = [
            'engagement_id' => $engagement,
            'direction' => 'in',
            'occurred_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'source' => 'mobile',
            'idempotency_key' => 'attendance-working-00001',
        ];
        $event = $this->postJson($base.'/attendance', $eventData)
            ->assertCreated()->assertJsonPath('data.event.status', 'submitted')
            ->json('data.event.id');
        $this->postJson($base.'/attendance/'.$event.'/approve')->assertForbidden();

        Sanctum::actingAs($hr);
        $this->postJson($base.'/leave/requests/'.$leave.'/decide', ['decision' => 'approved'])
            ->assertOk()->assertJsonPath('data.request.status', 'approved');
        $this->postJson($base.'/attendance/'.$event.'/approve')->assertOk()
            ->assertJsonPath('data.event.status', 'approved');
        $this->assertDatabaseCount('people_attendance_events', 1);
    }

    public function test_payroll_requires_policy_and_independent_approval_and_private_locked_payslip(): void
    {
        [$owner, $hr, $worker, $payroll, $spaceId, $engagement, $base] = $this->businessWithWorkers();
        $policy = $this->postJson($base.'/payroll/policies', [
            'policy_version' => 'UG-2026-review-1',
            'effective_from' => '2026-01-01',
            'country' => 'UG',
            'statutory_rules' => [
                'reference' => 'Approved statutory review document',
                'effective_from' => '2026-01-01',
            ],
        ])->assertCreated()->assertJsonPath('data.statutory_calculation_available', false)
            ->json('data.policy_id');
        $this->postJson($base.'/payroll/policies/'.$policy.'/approve')->assertForbidden();

        Sanctum::actingAs($payroll);
        $this->postJson($base.'/payroll/policies/'.$policy.'/approve')->assertOk();

        Sanctum::actingAs($owner);
        $period = $this->postJson($base.'/payroll/periods', [
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payroll_policy_id' => $policy,
        ])->assertCreated()->json('data.period.id');
        $this->postJson($base.'/payroll/periods/'.$period.'/lines', [
            'engagement_id' => $engagement,
            'gross_minor' => 500000,
            'deductions_minor' => 60000,
        ])->assertCreated()->assertJsonPath('data.line.net_minor', 440000);
        $this->postJson($base.'/payroll/periods/'.$period.'/prepare')->assertOk();
        $this->postJson($base.'/payroll/periods/'.$period.'/approve')->assertForbidden();

        Sanctum::actingAs($worker);
        $this->getJson($base.'/payroll/my-payslips')->assertOk()->assertJsonCount(0, 'data.payslips');
        $this->getJson($base.'/payroll/periods/'.$period.'/lines')->assertForbidden();

        Sanctum::actingAs($payroll);
        $this->postJson($base.'/payroll/periods/'.$period.'/approve')->assertOk();
        $this->postJson($base.'/payroll/periods/'.$period.'/lock')->assertOk()
            ->assertJsonPath('data.payment_instructions_created', false);
        $this->postJson($base.'/payroll/periods/'.$period.'/lines', [
            'engagement_id' => $engagement,
            'gross_minor' => 900000,
            'deductions_minor' => 0,
        ])->assertStatus(409);

        Sanctum::actingAs($worker);
        $this->getJson($base.'/payroll/my-payslips')->assertOk()
            ->assertJsonPath('data.payslips.0.net_minor', 440000);
    }

    public function test_asset_kpi_appraisal_reward_and_field_work_have_separate_reviews(): void
    {
        [$owner, $hr, $worker, $payroll, $spaceId, $engagement, $base] = $this->businessWithWorkers();
        $assetId = $this->postJson('/api/financial-spaces/'.$spaceId.'/assets', [
            'asset_type' => 'equipment',
            'name' => 'Project tablet',
            'value_minor' => 200000,
        ])->assertCreated()->json('data.id');
        $custody = $this->postJson($base.'/assets/custody', [
            'engagement_id' => $engagement,
            'financial_asset_id' => $assetId,
            'issued_on' => '2026-10-01',
        ])->assertCreated()->json('data.custody.id');
        $this->postJson($base.'/assets/custody', [
            'engagement_id' => $engagement,
            'financial_asset_id' => $assetId,
            'issued_on' => '2026-10-01',
        ])->assertStatus(409);

        Sanctum::actingAs($worker);
        $this->postJson($base.'/assets/custody/'.$custody.'/acknowledge')->assertOk();

        Sanctum::actingAs($hr);
        $kpi = $this->postJson($base.'/kpis', [
            'engagement_id' => $engagement,
            'metric' => 'Verified visits',
            'unit' => 'visits',
            'target_value' => 12,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
        ])->assertCreated()->json('data.kpi.id');
        $task = $this->postJson($base.'/field-assignments', [
            'engagement_id' => $engagement,
            'title' => 'Record supported household visits',
            'due_on' => '2026-10-25',
        ])->assertCreated()->json('data.task.id');
        $reward = $this->postJson($base.'/rewards', [
            'engagement_id' => $engagement,
            'reward_type' => 'recognition',
            'recognition' => 'Consistent attendance and documented field visits',
        ])->assertCreated()->json('data.reward.id');
        $this->postJson($base.'/rewards/'.$reward.'/approve')->assertForbidden();

        Sanctum::actingAs($worker);
        $this->postJson($base.'/kpis/'.$kpi.'/report', [
            'actual_value' => 10,
            'evidence_reference' => 'field-evidence-2026-10',
        ])->assertOk();
        $appraisal = $this->postJson($base.'/appraisals', [
            'engagement_id' => $engagement,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'self_assessment' => 'Completed the majority of scheduled field assignments.',
        ])->assertCreated()->json('data.appraisal.id');
        $this->postJson($base.'/field-assignments/'.$task.'/submit', [
            'evidence_reference' => 'protected-field-evidence-01',
            'claimed_expense_minor' => 10000,
        ])->assertOk();

        Sanctum::actingAs($hr);
        $this->postJson($base.'/kpis/'.$kpi.'/approve')->assertOk();
        $this->postJson($base.'/appraisals/'.$appraisal.'/approve', [
            'manager_assessment' => 'Evidence reviewed, progress is satisfactory.',
        ])->assertOk();
        $this->postJson($base.'/field-assignments/'.$task.'/approve')->assertOk()
            ->assertJsonPath('data.expense_reimbursement_created', false);

        Sanctum::actingAs($owner);
        $this->postJson($base.'/rewards/'.$reward.'/approve')->assertOk()
            ->assertJsonPath('data.payment_instruction_created', false);
        $this->postJson($base.'/assets/custody/'.$custody.'/return', [
            'returned_on' => '2026-10-21',
        ])->assertOk()->assertJsonPath('data.custody.status', 'returned');
    }
}
