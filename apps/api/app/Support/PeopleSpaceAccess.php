<?php

namespace App\Support;

use App\Models\FinancialSpace;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PeopleSpaceAccess
{
    private const HR_ROLES = ['owner', 'administrator', 'director', 'hr_admin', 'people_admin'];

    private const PAYROLL_ROLES = ['owner', 'administrator', 'director', 'payroll_manager'];

    private const SUPERVISOR_ROLES = ['owner', 'administrator', 'director', 'hr_admin', 'people_admin', 'supervisor', 'manager'];

    public function role(Request $request, FinancialSpace $space): string
    {
        abort_unless(
            $space->type === 'business' && $space->status === 'active' && $space->deleted_at === null,
            404
        );
        $role = DB::table('financial_space_memberships')
            ->where('financial_space_id', $space->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('role');

        abort_unless(is_string($role) && $role !== '', 403);

        return $role;
    }

    public function isHr(Request $request, FinancialSpace $space): bool
    {
        return in_array($this->role($request, $space), self::HR_ROLES, true);
    }

    public function hr(Request $request, FinancialSpace $space): void
    {
        abort_unless($this->isHr($request, $space), 403, 'HR administrator required.');
    }

    public function payroll(Request $request, FinancialSpace $space): void
    {
        abort_unless(
            in_array($this->role($request, $space), self::PAYROLL_ROLES, true),
            403,
            'Payroll authorisation required.'
        );
    }

    public function isSupervisor(Request $request, FinancialSpace $space): bool
    {
        return in_array($this->role($request, $space), self::SUPERVISOR_ROLES, true);
    }

    public function engagement(
        Request $request,
        FinancialSpace $space,
        int $id,
        bool $allowManager = false
    ): object {
        $role = $this->role($request, $space);
        $employment = DB::table('people_engagements')
            ->where('business_space_id', $space->id)
            ->where('id', $id)
            ->first();
        abort_unless($employment, 404);

        $self = (int) $employment->user_id === (int) $request->user()->id;
        $hr = in_array($role, self::HR_ROLES, true);
        $supervisor = $allowManager
            && in_array($role, self::SUPERVISOR_ROLES, true)
            && (int) $employment->supervisor_user_id === (int) $request->user()->id;
        abort_unless($self || $hr || $supervisor, 403);

        return $employment;
    }

    public function recordsForActor(
        Request $request,
        FinancialSpace $space,
        Builder $query,
        string $engagementColumn = 'engagement_id',
        bool $includeTeam = false
    ): Builder {
        $role = $this->role($request, $space);
        $query->where('business_space_id', $space->id);
        if (in_array($role, self::HR_ROLES, true)) {
            return $query;
        }

        $userId = $request->user()->id;
        $ids = DB::table('people_engagements')
            ->where('business_space_id', $space->id)
            ->where(function (Builder $scope) use ($userId, $includeTeam, $role) {
                $scope->where('user_id', $userId);
                if ($includeTeam && in_array($role, self::SUPERVISOR_ROLES, true)) {
                    $scope->orWhere('supervisor_user_id', $userId);
                }
            })->select('id');

        return $query->whereIn($engagementColumn, $ids);
    }

    public function audit(Request $request, FinancialSpace $space, string $action, string $subject, int $id): void
    {
        DB::table('people_audit_events')->insert([
            'business_space_id' => $space->id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => $subject,
            'subject_id' => $id,
            'created_at' => now(),
        ]);
    }
}
