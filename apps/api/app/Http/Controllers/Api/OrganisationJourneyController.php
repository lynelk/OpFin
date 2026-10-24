<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialSpace;
use App\Support\FinancialSpaceRolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganisationJourneyController extends Controller
{
    private const INSTITUTION_TYPES = ['business', 'sacco', 'investment_fund', 'partner'];

    public function onboarding(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->admin($request, $space);
        abort_unless(in_array($space->type, self::INSTITUTION_TYPES, true), 422, 'This Space does not use institutional onboarding.');

        $values = $request->validate([
            'stage' => ['sometimes', Rule::in(['profile', 'kyb', 'regulatory', 'products', 'integration', 'certification'])],
            'requirements' => ['sometimes', 'nullable', 'array'],
            'evidence' => ['sometimes', 'nullable', 'array'],
            'submit' => ['sometimes', 'boolean'],
        ]);

        // Lock the Space even on first creation, where no onboarding row yet
        // exists to lock. This serialises concurrent edits without a parallel
        // onboarding/financial service or a guessed activation record.
        $case = DB::transaction(function () use ($request, $space, $values) {
            DB::table('financial_spaces')->where('id', $space->id)->lockForUpdate()->firstOrFail();

            $existing = DB::table('organisation_onboarding_cases')
                ->where('financial_space_id', $space->id)
                ->orderBy('id')
                ->first();

            abort_if(
                $existing && $existing->status !== 'draft',
                409,
                'Submitted onboarding information is locked for review. Request an authorised review or correction.'
            );

            $stage = $values['stage'] ?? ($existing->stage ?? 'profile');
            $submit = $values['submit'] ?? false;
            abort_if($submit && $stage !== 'certification', 422, 'Complete the certification stage before submitting for review.');

            $changes = [
                'organisation_type' => $space->type,
                'stage' => $stage,
                'submitted_by_user_id' => $request->user()->id,
                'status' => $submit ? 'submitted' : 'draft',
                'updated_at' => now(),
            ];
            if (array_key_exists('requirements', $values)) {
                $changes['requirements'] = $values['requirements'] === null
                    ? null : json_encode($values['requirements'], JSON_THROW_ON_ERROR);
            }
            if (array_key_exists('evidence', $values)) {
                $changes['evidence'] = $values['evidence'] === null
                    ? null : json_encode($values['evidence'], JSON_THROW_ON_ERROR);
            }
            if ($submit) {
                $changes['submitted_at'] = now();
            }

            if ($existing) {
                DB::table('organisation_onboarding_cases')->where('id', $existing->id)->update($changes);

                return DB::table('organisation_onboarding_cases')->find($existing->id);
            }

            $id = DB::table('organisation_onboarding_cases')->insertGetId(array_merge(
                ['financial_space_id' => $space->id, 'created_at' => now()],
                $changes
            ));

            return DB::table('organisation_onboarding_cases')->find($id);
        });

        return response()->json(['data' => [
            'saved' => true,
            'space_type' => $space->type,
            'stage' => $case->stage,
            'status' => $case->status,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function enableEmployer(Request $request, FinancialSpace $space): JsonResponse
    {
        $this->admin($request, $space);
        abort_unless($space->type === 'business', 422, 'Employer is a Business capability.');

        DB::table('financial_space_capabilities')->updateOrInsert(
            ['financial_space_id' => $space->id, 'capability_key' => 'employer_services'],
            [
                'enabled' => true,
                'status' => 'configured',
                'configuration' => json_encode(['privacy_boundary' => 'personal_space_private'], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // This records an organisation configuration, not approval for
        // statutory payroll, salary deductions or other financial transfers.
        return response()->json(['data' => ['employer_services' => true, 'status' => 'configured']])
            ->header('Cache-Control', 'private, no-store');
    }

    public function workspace(Request $request, FinancialSpace $space): JsonResponse
    {
        $role = $this->memberRole($request, $space);
        $capabilities = DB::table('financial_space_capabilities')
            ->where('financial_space_id', $space->id)
            ->where('enabled', true)
            ->pluck('capability_key');
        $members = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        // Registration numbers, KYB evidence and verification requirements
        // are institution-admin records. Ordinary members and employees
        // must not gain them merely by having an active Space membership.
        $case = FinancialSpaceRolePolicy::canManageMembers($role)
            ? DB::table('organisation_onboarding_cases')
                ->where('financial_space_id', $space->id)
                ->orderBy('id')
                ->first()
            : null;

        return response()->json(['data' => [
            'space' => $space,
            'active_members' => $members,
            'capabilities' => $capabilities,
            'onboarding' => $case,
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function memberRole(Request $request, FinancialSpace $space): string
    {
        abort_unless($space->status === 'active' && $space->deleted_at === null, 403);

        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');

        abort_unless(is_string($role) && $role !== '', 403);

        return $role;
    }

    private function admin(Request $request, FinancialSpace $space): void
    {
        abort_unless(FinancialSpaceRolePolicy::canManageMembers($this->memberRole($request, $space)), 403);
    }
}
