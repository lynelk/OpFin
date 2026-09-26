<?php

namespace App\Services\ClubAccounting;

use App\Models\ClubBook;
use App\Models\FinancialSpace;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ClubAccess
{
    public const MAKER_ROLES = ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'secretary', 'director', 'manager'];

    public const CHECKER_ROLES = ['owner', 'administrator', 'admin', 'chairperson', 'treasurer', 'director', 'manager'];

    public function role(FinancialSpace $space, User $actor): string
    {
        if ($actor->deleted_at !== null || $space->deleted_at !== null || $space->status !== 'active') {
            throw new AuthorizationException('An active account and Financial Space are required.');
        }
        if (! in_array($space->type, ['investment_club', 'savings_group', 'sacco', 'cooperative', 'fund', 'investment_fund'], true)) {
            throw new AuthorizationException('Club accounting requires an eligible organisation Financial Space.');
        }
        $role = DB::table('financial_space_memberships')->where('financial_space_id', $space->id)
            ->where('user_id', $actor->id)->where('status', 'active')->whereNull('deleted_at')->value('role');
        if (! is_string($role)) {
            throw new AuthorizationException('You are not an active member of this Financial Space.');
        }

        return $role;
    }

    public function book(ClubBook $book, User $actor, bool $write = false, bool $approve = false): FinancialSpace
    {
        $space = FinancialSpace::query()->findOrFail($book->financial_space_id);
        $role = $this->role($space, $actor);
        if (($write && ! in_array($role, self::MAKER_ROLES, true))
            || ($approve && ! in_array($role, self::CHECKER_ROLES, true))) {
            throw new AuthorizationException('Your Financial Space role does not permit this accounting action.');
        }

        return $space;
    }

    public function isOfficer(ClubBook $book, User $actor): bool
    {
        return in_array($this->role(FinancialSpace::findOrFail($book->financial_space_id), $actor), self::MAKER_ROLES, true);
    }

    public function member(ClubBook $book, int $userId): void
    {
        $active = DB::table('financial_space_memberships as membership')
            ->join('users as member', 'member.id', '=', 'membership.user_id')
            ->where('membership.financial_space_id', $book->financial_space_id)
            ->where('membership.user_id', $userId)->where('membership.status', 'active')
            ->whereNull('membership.deleted_at')->whereNull('member.deleted_at')->exists();
        if (! $active) {
            throw new AuthorizationException('The selected member is not active in this Financial Space.');
        }
    }
}
