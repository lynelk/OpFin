<?php

declare(strict_types=1);

namespace App\Services\FinancialIntelligence;

use App\Models\FinancialSpace;
use App\Models\FinancialSpaceMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    public const ADMIN_ROLES = ['owner', 'administrator', 'director', 'chairperson', 'treasurer'];
    public const ROLES = ['analyst', 'reviewer', 'collections', 'board', 'auditor'];
    private const PERMISSIONS = [
        'administrator' => ['overview', 'source', 'import', 'publish', 'detail', 'case', 'report', 'grant', 'statement', 'share'],
        'analyst' => ['overview', 'import', 'detail', 'case', 'report', 'statement'],
        'reviewer' => ['overview', 'publish', 'detail', 'case', 'report', 'statement'],
        'collections' => ['case'],
        'board' => ['overview', 'report'],
        'auditor' => ['overview', 'detail', 'report'],
    ];

    public function role(FinancialSpace $space, User $user): string
    {
        abort_unless(config('financial_intelligence.enabled', false), 503, 'Financial Intelligence is not activated.');
        abort_if($space->trashed() || $user->trashed() || $space->status !== 'active', 403);
        abort_unless(in_array($space->type, ['business', 'sacco', 'investment_fund', 'partner'], true), 403, 'Use an authorised institutional Financial Space.');
        $membership = FinancialSpaceMembership::query()->where('financial_space_id', $space->id)
            ->where('user_id', $user->id)->where('status', 'active')->first();
        abort_unless($membership, 403);
        $entitled = DB::table('financial_space_entitlements')->where('financial_space_id', $space->id)
            ->where('entitlement_key', config('financial_intelligence.entitlement'))
            ->where('status', 'active')->where('starts_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists();
        abort_unless($entitled, 403, 'An active institutional Financial Intelligence entitlement is required.');
        if (in_array($membership->role, self::ADMIN_ROLES, true)) {
            return 'administrator';
        }
        $grant = DB::table('fi_grants')->where('financial_space_id', $space->id)->where('user_id', $user->id)
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_unless($grant && in_array($grant->role, self::ROLES, true), 403);

        return $grant->role;
    }

    public function require(FinancialSpace $space, User $user, string $permission): string
    {
        if ($permission === 'statement' && $space->type === 'personal') {
            abort_unless(config('financial_intelligence.enabled', false), 503, 'Financial Intelligence is not activated.');
            abort_if($space->trashed() || $user->trashed() || $space->status !== 'active', 403);
            abort_unless(FinancialSpaceMembership::query()->where('financial_space_id', $space->id)
                ->where('user_id', $user->id)->where('status', 'active')->where('role', 'owner')->exists(), 403);
            return 'personal';
        }
        $role = $this->role($space, $user);
        abort_unless(in_array($permission, self::PERMISSIONS[$role], true), 403);

        return $role;
    }

    public function permissions(string $role): array
    {
        return self::PERMISSIONS[$role] ?? [];
    }

    public function assertAssignee(FinancialSpace $space, int $userId): void
    {
        $member = FinancialSpaceMembership::query()->where('financial_space_id', $space->id)
            ->where('user_id', $userId)->where('status', 'active')->whereHas('user')->first();
        abort_unless($member, 422, 'Choose an active member of this Financial Space.');
        if (in_array($member->role, self::ADMIN_ROLES, true)) {
            return;
        }
        abort_unless(DB::table('fi_grants')->where('financial_space_id', $space->id)->where('user_id', $userId)
            ->whereIn('role', ['analyst', 'reviewer', 'collections'])->whereNull('revoked_at')->where('expires_at', '>', now())->exists(), 422, 'The assignee needs a current case-management grant.');
    }
}
