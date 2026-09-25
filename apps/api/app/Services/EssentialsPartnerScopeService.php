<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EssentialsPartnerScopeService
{
    private const DENIED = 'The customer has not authorised this operation in an active Financial Space.';

    public function __construct(
        private readonly PersonalFinancialSpaceService $personalSpaces,
        private readonly EssentialsOrchestrationService $essentials,
    ) {}

    public function resolve(User $customer, int $partnerAccountId, string $scope, ?int $requestedSpaceId): int
    {
        if ($customer->deleted_at !== null) {
            throw new InvalidArgumentException(self::DENIED);
        }
        $spaceId = $requestedSpaceId ?? $this->personalSpaces->find($customer)?->id;
        if (! $spaceId || $spaceId <= 0) {
            throw new InvalidArgumentException(self::DENIED);
        }

        // An unauthorised partner must not learn whether this customer belongs
        // to a particular Space. Check the exact grant before membership.
        try {
            $this->essentials->assertPartnerCustomerAuthorised($customer, $partnerAccountId, $scope, (int) $spaceId);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException(self::DENIED);
        }
        $membership = DB::table('financial_space_memberships as membership')
            ->join('financial_spaces as space', 'space.id', '=', 'membership.financial_space_id')
            ->where('membership.user_id', $customer->id)
            ->where('membership.financial_space_id', $spaceId)
            ->where('membership.status', 'active')
            ->whereNull('membership.deleted_at')
            ->where('space.status', 'active')
            ->whereNull('space.deleted_at')
            ->exists();
        if (! $membership) {
            // A formerly valid grant cannot override membership removal; use
            // exactly the same denial as an absent or revoked partner grant.
            throw new InvalidArgumentException(self::DENIED);
        }

        return (int) $spaceId;
    }
}
