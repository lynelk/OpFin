<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\PeopleSpaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeopleOperationsController extends Controller
{
    public function __construct(private readonly PeopleSpaceAccess $access) {}

    public function assets(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->listing($request, $space, 'people_asset_custodies');
    }

    public function assignAsset(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->hr($request, $space);
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'financial_asset_id' => ['required', 'integer'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id']);
        abort_unless($employee->status === 'active', 409);

        $owned = DB::table('financial_assets')
            ->where('id', $data['financial_asset_id'])
            ->where('financial_space_id', $space->id)
            ->where('status', 'active')->whereNull('deleted_at')->exists();
        abort_unless($owned, 422, 'Asset must belong to the employer Financial Space.');

        $id = DB::transaction(function () use ($data, $request, $space) {
            abort_if(
                DB::table('people_asset_custodies')
                    ->where('business_space_id', $space->id)
                    ->where('financial_asset_id', $data['financial_asset_id'])
                    ->where('status', 'issued')
                    ->lockForUpdate()->exists(),
                409,
                'Asset is currently assigned to another worker.'
            );

            return DB::table('people_asset_custodies')->insertGetId([
                ...$data,
                'business_space_id' => $space->id,
                'status' => 'issued',
                'issued_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->access->audit($request, $space, 'asset.issued', 'asset_custody', $id);

        return response()->json(['data' => ['custody' => DB::table('people_asset_custodies')->find($id)]], 201);
    }

    public function acknowledgeAsset(Request $request, FinancialSpace $space, int $custody): JsonResponse
    {
        $row = $this->ownedRecord($space, 'people_asset_custodies', $custody);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id);
        abort_unless((int) $employee->user_id === (int) $request->user()->id, 403);
        abort_unless($row->status === 'issued', 409);
        DB::table('people_asset_custodies')->where('id', $custody)->update([
            'acknowledged_by_user_id' => $request->user()->id,
            'acknowledged_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'asset.acknowledged', 'asset_custody', $custody);

        return response()->json(['data' => ['custody' => $this->ownedRecord($space, 'people_asset_custodies', $custody)]]);
    }

    public function returnAsset(Request $request, FinancialSpace $space, int $custody): JsonResponse
    {
        $this->access->hr($request, $space);
        $data = $request->validate([
            'returned_on' => ['required', 'date_format:Y-m-d'],
        ]);
        $record = $this->ownedRecord($space, 'people_asset_custodies', $custody);
        abort_unless($record->status === 'issued', 409);
        DB::table('people_asset_custodies')->where('id', $custody)->update([
            'status' => 'returned',
            'returned_on' => $data['returned_on'],
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'asset.returned', 'asset_custody', $custody);

        return response()->json(['data' => ['custody' => $this->ownedRecord($space, 'people_asset_custodies', $custody)]]);
    }

    public function kpis(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->listing($request, $space, 'people_kpi_entries', true);
    }

    public function defineKpi(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'metric' => ['required', 'string', 'max:160'],
            'unit' => ['required', 'string', 'max:50'],
            'target_value' => ['required', 'numeric', 'min:0', 'max:10000000000'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id'], true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        $id = DB::table('people_kpi_entries')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'status' => 'draft',
            'recorded_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'kpi.defined', 'kpi', $id);

        return response()->json(['data' => ['kpi' => $this->ownedRecord($space, 'people_kpi_entries', $id)]], 201);
    }

    public function reportKpi(Request $request, FinancialSpace $space, int $kpi): JsonResponse
    {
        $data = $request->validate([
            'actual_value' => ['required', 'numeric', 'min:0', 'max:10000000000'],
            'evidence_reference' => ['required', 'string', 'max:160'],
        ]);
        $row = $this->ownedRecord($space, 'people_kpi_entries', $kpi);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id);
        abort_unless((int) $employee->user_id === (int) $request->user()->id, 403);
        abort_unless($row->status === 'draft', 409);
        DB::table('people_kpi_entries')->where('id', $kpi)->update([
            ...$data,
            'status' => 'submitted',
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'kpi.submitted', 'kpi', $kpi);

        return response()->json(['data' => ['kpi' => $this->ownedRecord($space, 'people_kpi_entries', $kpi)]]);
    }

    public function approveKpi(Request $request, FinancialSpace $space, int $kpi): JsonResponse
    {
        $row = $this->ownedRecord($space, 'people_kpi_entries', $kpi);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id, true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        abort_unless($row->status === 'submitted', 409);
        DB::table('people_kpi_entries')->where('id', $kpi)->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'kpi.approved', 'kpi', $kpi);

        return response()->json(['data' => ['kpi' => $this->ownedRecord($space, 'people_kpi_entries', $kpi)]]);
    }

    public function appraisals(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->listing($request, $space, 'people_appraisals', true);
    }

    public function createAppraisal(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'self_assessment' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id']);
        abort_unless((int) $employee->user_id === (int) $request->user()->id, 403);
        $id = DB::table('people_appraisals')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'status' => 'submitted',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'appraisal.submitted', 'appraisal', $id);

        return response()->json(['data' => ['appraisal' => $this->ownedRecord($space, 'people_appraisals', $id)]], 201);
    }

    public function approveAppraisal(Request $request, FinancialSpace $space, int $appraisal): JsonResponse
    {
        $data = $request->validate([
            'manager_assessment' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $row = $this->ownedRecord($space, 'people_appraisals', $appraisal);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id, true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        abort_unless($row->status === 'submitted', 409);
        abort_unless(
            DB::table('people_kpi_entries')
                ->where('business_space_id', $space->id)
                ->where('engagement_id', $row->engagement_id)
                ->whereDate('period_start', '<=', $row->period_end)
                ->whereDate('period_end', '>=', $row->period_start)
                ->where('status', 'approved')->exists(),
            422,
            'At least one approved KPI in this appraisal period is required.'
        );
        DB::table('people_appraisals')->where('id', $appraisal)->update([
            'manager_assessment' => $data['manager_assessment'],
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'appraisal.approved', 'appraisal', $appraisal);

        return response()->json(['data' => ['appraisal' => $this->ownedRecord($space, 'people_appraisals', $appraisal)]]);
    }

    public function rewards(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->listing($request, $space, 'people_rewards', true);
    }

    public function nominateReward(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'reward_type' => ['required', Rule::in(['recognition', 'bonus', 'allowance'])],
            'recognition' => ['required', 'string', 'min:10', 'max:500'],
            'proposed_amount_minor' => ['sometimes', 'integer', 'min:0'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id'], true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        $id = DB::table('people_rewards')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'proposed_amount_minor' => $data['proposed_amount_minor'] ?? 0,
            'currency' => $space->currency,
            'status' => 'nominated',
            'nominated_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'reward.nominated', 'reward', $id);

        return response()->json(['data' => ['reward' => $this->ownedRecord($space, 'people_rewards', $id)]], 201);
    }

    public function approveReward(Request $request, FinancialSpace $space, int $reward): JsonResponse
    {
        $this->access->hr($request, $space);
        $row = $this->ownedRecord($space, 'people_rewards', $reward);
        abort_unless($row->status === 'nominated', 409);
        abort_unless($row->nominated_by_user_id !== $request->user()->id, 403);
        // Monetary rewards still require budget/revenue and statutory checks
        // before any ledger or payroll instruction; this is approval only.
        DB::table('people_rewards')->where('id', $reward)->update([
            'status' => 'approved_not_paid',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'reward.approved', 'reward', $reward);

        return response()->json(['data' => [
            'reward' => $this->ownedRecord($space, 'people_rewards', $reward),
            'payment_instruction_created' => false,
        ]]);
    }

    public function fieldAssignments(Request $request, FinancialSpace $space): JsonResponse
    {
        return $this->listing($request, $space, 'people_field_assignments', true);
    }

    public function assignFieldWork(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id'], true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        $id = DB::table('people_field_assignments')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'currency' => $space->currency,
            'status' => 'assigned',
            'assigned_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'field_task.assigned', 'field_task', $id);

        return response()->json(['data' => ['task' => $this->ownedRecord($space, 'people_field_assignments', $id)]], 201);
    }

    public function submitFieldWork(Request $request, FinancialSpace $space, int $task): JsonResponse
    {
        $data = $request->validate([
            'evidence_reference' => ['required', 'string', 'max:160'],
            'claimed_expense_minor' => ['sometimes', 'integer', 'min:0'],
        ]);
        $row = $this->ownedRecord($space, 'people_field_assignments', $task);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id);
        abort_unless((int) $employee->user_id === (int) $request->user()->id, 403);
        abort_unless($row->status === 'assigned', 409);
        DB::table('people_field_assignments')->where('id', $task)->update([
            ...$data,
            'status' => 'submitted',
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'field_task.submitted', 'field_task', $task);

        return response()->json(['data' => ['task' => $this->ownedRecord($space, 'people_field_assignments', $task)]]);
    }

    public function approveFieldWork(Request $request, FinancialSpace $space, int $task): JsonResponse
    {
        $row = $this->ownedRecord($space, 'people_field_assignments', $task);
        $employee = $this->access->engagement($request, $space, (int) $row->engagement_id, true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
        abort_unless($row->status === 'submitted', 409);
        DB::table('people_field_assignments')->where('id', $task)->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'field_task.approved', 'field_task', $task);

        return response()->json(['data' => [
            'task' => $this->ownedRecord($space, 'people_field_assignments', $task),
            'expense_reimbursement_created' => false,
        ]]);
    }

    private function listing(
        Request $request,
        FinancialSpace $space,
        string $table,
        bool $includeTeam = false
    ): JsonResponse {
        return response()->json(['data' => [
            'records' => $this->access->recordsForActor(
                $request, $space, DB::table($table), 'engagement_id', $includeTeam
            )->orderByDesc('id')->paginate(40),
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function ownedRecord(FinancialSpace $space, string $table, int $id): object
    {
        return DB::table($table)
            ->where('business_space_id', $space->id)
            ->where('id', $id)->first() ?? abort(404);
    }
}
