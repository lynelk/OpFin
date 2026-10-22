<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\PeopleSpaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PeopleWorkforceController extends Controller
{
    public function __construct(private readonly PeopleSpaceAccess $access) {}

    public function employees(Request $request, FinancialSpace $space): JsonResponse
    {
        $query = $this->access->recordsForActor(
            $request, $space, DB::table('people_engagements'), 'id', true
        );

        return response()->json(['data' => [
            'employees' => $query
                ->select(['id', 'public_id', 'user_id', 'workforce_number', 'engagement_type',
                    'job_title', 'department', 'supervisor_user_id', 'starts_on', 'ends_on', 'status'])
                ->orderBy('id')->paginate(30),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function createEmployee(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->hr($request, $space);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'workforce_number' => ['required', 'string', 'max:60'],
            'engagement_type' => ['required', Rule::in([
                'permanent', 'fixed_term', 'casual', 'contractor', 'consultant', 'intern',
                'seasonal', 'volunteer', 'shift', 'agent',
            ])],
            'job_title' => ['required', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:120'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $this->activeMember($space, (int) $data['user_id']);
        if (isset($data['supervisor_user_id'])) {
            $this->activeMember($space, (int) $data['supervisor_user_id']);
        }

        $id = DB::table('people_engagements')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'public_id' => (string) Str::uuid(),
            'status' => 'active',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'employment.created', 'engagement', $id);

        return response()->json(['data' => [
            'employee' => DB::table('people_engagements')->find($id),
        ]], 201);
    }

    public function leavePolicies(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->role($request, $space);

        return response()->json(['data' => [
            'policies' => DB::table('people_leave_policies')
                ->where('business_space_id', $space->id)
                ->where('status', 'approved')
                ->orderByDesc('effective_from')->get([
                    'id', 'leave_type', 'effective_from', 'annual_days', 'status',
                ]),
        ]]);
    }

    public function createLeavePolicy(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->hr($request, $space);
        $data = $request->validate([
            'leave_type' => ['required', 'string', 'max:60'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'annual_days' => ['required', 'integer', 'min:0', 'max:366'],
        ]);
        $id = DB::table('people_leave_policies')->insertGetId([
            'business_space_id' => $space->id,
            ...$data,
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'leave_policy.drafted', 'leave_policy', $id);

        return response()->json(['data' => ['policy' => DB::table('people_leave_policies')->find($id)]], 201);
    }

    public function approveLeavePolicy(Request $request, FinancialSpace $space, int $policy): JsonResponse
    {
        $this->access->hr($request, $space);
        $query = DB::table('people_leave_policies')
            ->where('business_space_id', $space->id)->where('id', $policy);
        $row = $query->first();
        abort_unless($row, 404);
        abort_unless($row->status === 'draft', 409);
        abort_unless($row->created_by_user_id !== $request->user()->id, 403);
        $query->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'leave_policy.approved', 'leave_policy', $policy);

        $updated = DB::table('people_leave_policies')
            ->where('business_space_id', $space->id)
            ->where('id', $policy)->first();

        return response()->json(['data' => ['policy' => $updated]]);
    }

    public function leaveRequests(Request $request, FinancialSpace $space): JsonResponse
    {
        return response()->json(['data' => [
            'requests' => $this->access->recordsForActor(
                $request, $space, DB::table('people_leave_requests'), 'engagement_id', true
            )->orderByDesc('id')->paginate(30),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function requestLeave(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'leave_type' => ['required', 'string', 'max:60'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'requested_days' => ['required', 'integer', 'min:1', 'max:366'],
        ]);
        $this->access->engagement($request, $space, (int) $data['engagement_id']);
        $start = CarbonImmutable::parse($data['starts_on']);
        $end = CarbonImmutable::parse($data['ends_on']);
        abort_if((int) $data['requested_days'] > $start->diffInDays($end) + 1, 422, 'Days cannot exceed the inclusive leave interval.');

        $active = DB::table('people_leave_policies')
            ->where('business_space_id', $space->id)
            ->where('leave_type', $data['leave_type'])
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $data['starts_on'])
            ->orderByDesc('effective_from')
            ->first();
        abort_unless($active, 422, 'An approved leave policy is required.');

        $overlap = DB::table('people_leave_requests')
            ->where('engagement_id', $data['engagement_id'])
            ->whereIn('status', ['requested', 'approved'])
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])->exists();
        abort_if($overlap, 409, 'This leave request overlaps an existing request.');

        $id = DB::table('people_leave_requests')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'status' => 'requested',
            'requested_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'leave.requested', 'leave_request', $id);

        return response()->json(['data' => ['request' => DB::table('people_leave_requests')->find($id)]], 201);
    }

    public function decideLeave(Request $request, FinancialSpace $space, int $leave): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ]);
        $record = DB::table('people_leave_requests')
            ->where('business_space_id', $space->id)->where('id', $leave)->first();
        abort_unless($record, 404);
        $this->access->engagement($request, $space, (int) $record->engagement_id, true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless($record->requested_by_user_id !== $request->user()->id, 403);
        abort_unless($record->status === 'requested', 409);
        $query = DB::table('people_leave_requests')
            ->where('business_space_id', $space->id)->where('id', $leave);
        $query->update([
            'status' => $data['decision'],
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'leave.'.$data['decision'], 'leave_request', $leave);

        return response()->json(['data' => ['request' => $query->first()]]);
    }

    public function attendance(Request $request, FinancialSpace $space): JsonResponse
    {
        return response()->json(['data' => [
            'events' => $this->access->recordsForActor(
                $request, $space, DB::table('people_attendance_events'), 'engagement_id', true
            )->orderByDesc('occurred_at')->paginate(60),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function clock(Request $request, FinancialSpace $space): JsonResponse
    {
        $data = $request->validate([
            'engagement_id' => ['required', 'integer'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'occurred_at' => ['required', 'date'],
            'source' => ['required', Rule::in(['mobile', 'manual'])],
            'idempotency_key' => ['required', 'string', 'min:12', 'max:120'],
        ]);
        $employee = $this->access->engagement($request, $space, (int) $data['engagement_id']);
        abort_unless($employee->status === 'active', 409);

        $observed = CarbonImmutable::parse($data['occurred_at'])->utc();
        abort_if(
            $observed->greaterThan(now()->addMinutes(5))
            || $observed->lessThan(now()->subDays(31)),
            422,
            'Attendance observation outside supported offline capture window.'
        );
        // Normalise the wire ISO timestamp before both the duplicate comparison
        // and persistence. Replayed offline events must not disagree because a
        // database timestamp drops ISO separators, fractional seconds or 'Z'.
        $data['occurred_at'] = $observed->format('Y-m-d H:i:s');

        $id = DB::transaction(function () use ($data, $request, $space) {
            $existing = DB::table('people_attendance_events')
                ->where('business_space_id', $space->id)
                ->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless(
                    (int) $existing->engagement_id === (int) $data['engagement_id']
                    && $existing->direction === $data['direction']
                    && $existing->occurred_at === $data['occurred_at'],
                    409,
                    'Idempotency key belongs to another attendance event.'
                );

                return $existing->id;
            }

            return DB::table('people_attendance_events')->insertGetId([
                ...$data,
                'business_space_id' => $space->id,
                'status' => 'submitted',
                'recorded_by_user_id' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->access->audit($request, $space, 'attendance.submitted', 'attendance_event', $id);

        return response()->json(['data' => [
            'event' => DB::table('people_attendance_events')->find($id),
        ]], 201);
    }

    public function approveClock(Request $request, FinancialSpace $space, int $event): JsonResponse
    {
        $record = DB::table('people_attendance_events')
            ->where('business_space_id', $space->id)->where('id', $event)->first();
        abort_unless($record, 404);
        $this->access->engagement($request, $space, (int) $record->engagement_id, true);
        abort_unless($this->access->isSupervisor($request, $space), 403);
        abort_unless($record->recorded_by_user_id !== $request->user()->id, 403);
        abort_unless($record->status === 'submitted', 409);
        DB::table('people_attendance_events')->where('id', $event)->update([
            'status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'attendance.approved', 'attendance_event', $event);

        return response()->json(['data' => [
            'event' => DB::table('people_attendance_events')->find($event),
        ]]);
    }

    public function biometricConnectors(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->hr($request, $space);

        return response()->json(['data' => [
            'connectors' => DB::table('people_biometric_connectors')
                ->where('business_space_id', $space->id)
                ->get(['id', 'vendor', 'external_device_reference',
                    'status', 'processing_approval_reference', 'processing_approved_at']),
        ]]);
    }

    public function registerBiometricConnector(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->access->hr($request, $space);
        $data = $request->validate([
            'vendor' => ['required', 'string', 'max:80'],
            'external_device_reference' => ['required', 'string', 'max:160'],
        ]);
        $id = DB::table('people_biometric_connectors')->insertGetId([
            ...$data,
            'business_space_id' => $space->id,
            'status' => 'pending_review',
            'configured_by_user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->access->audit($request, $space, 'biometric_device.registered', 'biometric_connector', $id);

        return response()->json(['data' => [
            'connector' => DB::table('people_biometric_connectors')->find($id),
            'event_ingestion_enabled' => false,
            'notice' => 'Enrolment and biometric matching remain disabled until privacy and vendor certification.',
        ]], 201);
    }

    private function activeMember(FinancialSpace $space, int $userId): void
    {
        abort_unless(
            DB::table('financial_space_memberships')
                ->where('financial_space_id', $space->id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('deleted_at')->exists(),
            422,
            'Worker must first accept an authorised Business Space invitation.'
        );
    }
}
