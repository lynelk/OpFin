<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\PeopleSpaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeoplePayrollController extends Controller
{
    public function __construct(private readonly PeopleSpaceAccess $access) {}

    public function policies(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->payroll($request, $space);

        return response()->json(['data' => [
            'policies' => DB::table('people_payroll_policies')
                ->where('business_space_id', $space->id)
                ->orderByDesc('effective_from')
                ->get(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function createPolicy(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->payroll($request, $space);
        $data = $request->validate([
            'policy_version' => ['required', 'string', 'max:80'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'country' => ['required', 'string', 'size:2'],
            'statutory_rules' => ['required', 'array', 'min:1'],
            'statutory_rules.reference' => ['required', 'string', 'max:240'],
            'statutory_rules.effective_from' => ['required', 'date_format:Y-m-d'],
        ]);
        abort_unless(strtoupper($data['country']) === $space->country, 422);
        $id = DB::table('people_payroll_policies')->insertGetId([
            'business_space_id' => $space->id,
            'policy_version' => $data['policy_version'],
            'effective_from' => $data['effective_from'],
            'country' => $space->country,
            'currency' => $space->currency,
            'statutory_rules' => json_encode($data['statutory_rules']),
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_policy.drafted', 'payroll_policy', $id);

        return response()->json(['data' => [
            'policy_id' => $id,
            'status' => 'draft',
            'statutory_calculation_available' => false,
            'notice' => 'Policy parameters require independent statutory verification before payroll calculations.',
        ]], 201);
    }

    public function approvePolicy(Request $request, FinancialSpace $space, int $policy): JsonResponse
    {
        $this->access->payroll($request, $space);
        $query = DB::table('people_payroll_policies')
            ->where('business_space_id', $space->id)->where('id', $policy);
        $record = $query->first();
        abort_unless($record, 404);
        abort_unless($record->status === 'draft', 409);
        abort_unless($record->created_by_user_id !== $request->user()->id, 403);

        $query->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_policy.approved', 'payroll_policy', $policy);

        return response()->json(['data' => [
            'policy_id' => $policy,
            'status' => 'approved',
            'automatic_statutory_calculation_enabled' => false,
        ]]);
    }

    public function periods(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->payroll($request, $space);

        return response()->json(['data' => [
            'periods' => DB::table('people_payroll_periods')
                ->where('business_space_id', $space->id)
                ->orderByDesc('period_start')->paginate(30),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function createPeriod(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->payroll($request, $space);
        $data = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'payroll_policy_id' => ['required', 'integer'],
        ]);
        $policy = DB::table('people_payroll_policies')
            ->where('business_space_id', $space->id)
            ->where('id', $data['payroll_policy_id'])
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $data['period_start'])->first();
        abort_unless($policy, 422, 'Effective-dated approved payroll policy required.');

        $id = DB::table('people_payroll_periods')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_period.drafted', 'payroll_period', $id);

        return response()->json(['data' => [
            'period' => DB::table('people_payroll_periods')->find($id),
        ]], 201);
    }

    public function lines(Request $request, FinancialSpace $space, int $period): JsonResponse
    {
        $this->access->payroll($request, $space);
        $this->period($space, $period);

        return response()->json(['data' => [
            'lines' => DB::table('people_payroll_lines')
                ->where('business_space_id', $space->id)
                ->where('payroll_period_id', $period)->paginate(100),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function createLine(Request $request, FinancialSpace $space, int $period): JsonResponse
    {
        $this->access->payroll($request, $space);
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'gross_minor' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
            'deductions_minor' => ['required', 'integer', 'min:0', 'max:9000000000000000'],
        ]);
        $record = $this->period($space, $period);
        abort_unless($record->status === 'draft', 409);
        abort_unless((int) $data['deductions_minor'] <= (int) $data['gross_minor'], 422);
        $engagement = DB::table('people_engagements')
            ->where('business_space_id', $space->id)
            ->where('id', $data['engagement_id'])
            ->where('status', 'active')->first();
        abort_unless($engagement, 422, 'An active worker in this organisation is required.');

        $lineId = DB::table('people_payroll_lines')->insertGetId([
            'business_space_id' => $space->id,
            'payroll_period_id' => $period,
            'engagement_id' => $data['engagement_id'],
            'gross_minor' => $data['gross_minor'],
            'deductions_minor' => $data['deductions_minor'],
            'net_minor' => $data['gross_minor'] - $data['deductions_minor'],
            'currency' => $space->currency,
            'status' => 'draft',
            'entered_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_line.drafted', 'payroll_line', $lineId);

        return response()->json(['data' => [
            'line' => DB::table('people_payroll_lines')->find($lineId),
            'statutory_calculation_verified' => false,
            'notice' => 'Gross and deductions are authorised manual inputs pending independently verified statutory computation.',
        ]], 201);
    }

    public function prepare(Request $request, FinancialSpace $space, int $period): JsonResponse
    {
        $this->access->payroll($request, $space);
        $record = $this->period($space, $period);
        abort_unless($record->status === 'draft', 409);
        abort_unless(
            DB::table('people_payroll_lines')->where('payroll_period_id', $period)
                ->where('business_space_id', $space->id)->exists(),
            422,
            'A payroll period requires at least one reviewed draft line.'
        );
        DB::table('people_payroll_periods')->where('id', $period)->update([
            'status' => 'prepared',
            'prepared_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_period.prepared', 'payroll_period', $period);

        return response()->json(['data' => ['period' => $this->period($space, $period)]]);
    }

    public function approve(Request $request, FinancialSpace $space, int $period): JsonResponse
    {
        $this->access->payroll($request, $space);
        $record = $this->period($space, $period);
        abort_unless($record->status === 'prepared', 409);
        abort_unless($record->created_by_user_id !== $request->user()->id, 403);
        abort_unless($record->prepared_by_user_id !== $request->user()->id, 403);

        DB::table('people_payroll_periods')->where('id', $period)->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'payroll_period.approved', 'payroll_period', $period);

        return response()->json(['data' => ['period' => $this->period($space, $period)]]);
    }

    public function lock(Request $request, FinancialSpace $space, int $period): JsonResponse
    {
        $this->access->payroll($request, $space);
        $record = $this->period($space, $period);
        abort_unless($record->status === 'approved', 409);
        abort_unless($record->created_by_user_id !== $request->user()->id, 403);
        DB::transaction(function () use ($period, $request, $space) {
            DB::table('people_payroll_periods')
                ->where('id', $period)->where('business_space_id', $space->id)
                ->where('status', 'approved')->update([
                    'status' => 'locked',
                    'locked_by_user_id' => $request->user()->id,
                    'locked_at' => now(),
                    'updated_at' => now(),
                ]);
            DB::table('people_payroll_lines')
                ->where('payroll_period_id', $period)
                ->where('business_space_id', $space->id)->update([
                    'status' => 'locked',
                    'updated_at' => now(),
                ]);
        });
        $this->access->audit($request, $space, 'payroll_period.locked', 'payroll_period', $period);

        return response()->json(['data' => [
            'period' => $this->period($space, $period),
            'payment_instructions_created' => false,
        ]]);
    }

    public function myPayslips(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->role($request, $space);

        return response()->json(['data' => [
            'payslips' => DB::table('people_payroll_lines as line')
                ->join('people_payroll_periods as period', 'period.id', '=', 'line.payroll_period_id')
                ->join('people_engagements as worker', 'worker.id', '=', 'line.engagement_id')
                ->where('worker.user_id', $request->user()->id)
                ->where('worker.business_space_id', $space->id)
                ->where('period.business_space_id', $space->id)
                ->where('period.status', 'locked')
                ->where('line.status', 'locked')
                ->orderByDesc('period.period_start')
                ->get(['line.id', 'line.gross_minor', 'line.deductions_minor', 'line.net_minor',
                    'line.currency', 'period.period_start', 'period.period_end', 'period.locked_at']),
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function period(FinancialSpace $space, int $period): object
    {
        return DB::table('people_payroll_periods')
            ->where('business_space_id', $space->id)->where('id', $period)
            ->first() ?? abort(404);
    }
}
