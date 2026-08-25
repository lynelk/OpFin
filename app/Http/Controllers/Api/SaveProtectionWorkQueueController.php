<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\SavingsMovement;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaveProtectionWorkQueueController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $savingsContributions = $this->tenantScope(
            SavingsMovement::query()
                ->with(['goal.product', 'mobileMoneyTransaction'])
                ->where('movement_type', SavingsMovement::TYPE_CONTRIBUTION)
                ->where('status', SavingsMovement::STATUS_COLLECTED_PENDING_PARTNER),
            $user,
        )->oldest('requested_at')->limit($limit)->get();

        $savingsWithdrawals = $this->tenantScope(
            SavingsMovement::query()
                ->with(['goal.product', 'mobileMoneyTransaction'])
                ->where('movement_type', SavingsMovement::TYPE_WITHDRAWAL)
                ->whereIn('status', [
                    SavingsMovement::STATUS_WITHDRAWAL_REQUESTED,
                    SavingsMovement::STATUS_PARTNER_RELEASED,
                ]),
            $user,
        )->oldest('requested_at')->limit($limit)->get();

        $protectionPremiums = $this->tenantScope(
            ProtectionPremiumPayment::query()
                ->with(['policy.product', 'mobileMoneyTransaction'])
                ->where('status', ProtectionPremiumPayment::STATUS_COLLECTED_PENDING_PARTNER),
            $user,
        )->oldest('requested_at')->limit($limit)->get();

        $protectionPolicies = $this->tenantScope(
            ProtectionPolicy::query()
                ->with(['product', 'premiumPayments'])
                ->where('status', ProtectionPolicy::STATUS_PENDING_ISSUANCE),
            $user,
        )->oldest('enrolled_at')->limit($limit)->get();

        $protectionClaims = $this->tenantScope(
            ProtectionClaim::query()
                ->with('policy.product')
                ->whereIn('status', [
                    ProtectionClaim::STATUS_SUBMITTED,
                    ProtectionClaim::STATUS_PARTNER_REVIEW,
                    ProtectionClaim::STATUS_DISPUTED,
                    ProtectionClaim::STATUS_APPROVED,
                ]),
            $user,
        )->oldest('submitted_at')->limit($limit)->get();

        return ApiResponse::success('Save & Protection operations work queue loaded.', [
            'counts' => [
                'savings_contributions' => $savingsContributions->count(),
                'savings_withdrawals' => $savingsWithdrawals->count(),
                'protection_premiums' => $protectionPremiums->count(),
                'protection_policies' => $protectionPolicies->count(),
                'protection_claims' => $protectionClaims->count(),
            ],
            'savings_contributions' => $savingsContributions,
            'savings_withdrawals' => $savingsWithdrawals,
            'protection_premiums' => $protectionPremiums,
            'protection_policies' => $protectionPolicies,
            'protection_claims' => $protectionClaims,
            'scope' => $user->role === User::ROLE_PLATFORM_ADMIN ? 'platform' : 'institution',
            'institution_id' => $user->role === User::ROLE_PLATFORM_ADMIN ? null : $user->institution_id,
        ]);
    }

    private function tenantScope(Builder $query, User $user): Builder
    {
        if ($user->role === User::ROLE_PLATFORM_ADMIN) {
            return $query;
        }

        return $query->where('institution_id', $user->institution_id);
    }
}
