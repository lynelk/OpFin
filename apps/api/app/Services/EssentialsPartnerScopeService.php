<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EssentialsPartnerScopeService
{
    public function __construct(
        private readonly PersonalFinancialSpaceService $personalSpaces,
        private readonly EssentialsOrchestrationService $essentials,
    ) {}

    public function resolve(User $customer, int $partnerAccountId, string $scope, ?int $requestedSpaceId): int
    {
        if ($customer->deleted_at !== null) {
            throw new InvalidArgumentException('This customer is not available for partner access.');
        }
        $spaceId = $requestedSpaceId ?? $this->personalSpaces->find($customer)?->id;
        if (! $spaceId || $spaceId <= 0) {
            throw new InvalidArgumentException('An existing authorised Financial Space is required.');
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
            throw new InvalidArgumentException('The customer is not an active member of the requested Financial Space.');
        }

        // Always pass the resolved positive identifier. A permission for one
        // Space cannot satisfy an omitted or different target Space.
        $this->essentials->assertPartnerCustomerAuthorised($customer, $partnerAccountId, $scope, (int) $spaceId);

        return (int) $spaceId;
    }
}
