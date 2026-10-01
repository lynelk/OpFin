<?php

namespace App\Services;

use App\Models\ProtectionPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MobileHomeService
{
    public function __construct(
        private readonly FinancialWellbeingService $wellbeing,
        private readonly CustomerCreditProfileService $creditProfiles,
        private readonly PersonalFinancialSpaceService $personalSpaces,
    ) {}

    public function snapshot(User $user, string $currency = 'UGX'): array
    {
        $this->personalSpaces->ensure($user);
        $currency = strtoupper($currency);

        // A stable five-minute observation point keeps conditional refresh
        // useful without hiding underlying changes: changed source data still
        // changes the response hash immediately.
        $now = CarbonImmutable::now();
        $asOf = $now->setTime(
            $now->hour,
            intdiv($now->minute, 5) * 5,
            0,
        );

        $spaces = DB::table('financial_space_memberships as memberships')
            ->join(
                'financial_spaces as spaces',
                'spaces.id',
                '=',
                'memberships.financial_space_id'
            )
            ->where('memberships.user_id', $user->id)
            ->where('memberships.status', 'active')
            ->whereNull('memberships.deleted_at')
            ->whereNull('spaces.deleted_at')
            ->select(
                'spaces.id',
                'spaces.public_id',
                'spaces.type',
                'spaces.name',
                'spaces.country',
                'spaces.currency',
                'spaces.status',
                'memberships.role'
            )
            ->orderByRaw("CASE WHEN spaces.type = 'personal' THEN 0 ELSE 1 END")
            ->orderBy('spaces.name')
            ->orderBy('spaces.id')
            ->get()
            ->map(fn ($space) => (array) $space)
            ->values()
            ->all();

        $credit = $this->creditProfiles->status($user);
        $policies = ProtectionPolicy::query()
            ->with([
                'product:id,code,name,insurer_name,underwriter_name,currency,product_type',
            ])
            ->where('user_id', $user->id)
            ->where('coverage_scope', 'personal')
            ->whereIn('status', [
                ProtectionPolicy::STATUS_PREMIUM_DUE,
                ProtectionPolicy::STATUS_PREMIUM_PENDING,
                ProtectionPolicy::STATUS_PENDING_ISSUANCE,
                ProtectionPolicy::STATUS_ACTIVE,
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(static function (ProtectionPolicy $policy): array {
                $product = $policy->product;

                return [
                    'id' => $policy->id,
                    'policy_reference' => $policy->policy_reference,
                    'external_policy_number' => $policy->external_policy_number,
                    'status' => $policy->status,
                    'premium_amount_minor' => (int) $policy->premium_amount_minor,
                    'premium_frequency' => $policy->premium_frequency,
                    'coverage_limit_minor' => $policy->coverage_limit_minor === null
                        ? null
                        : (int) $policy->coverage_limit_minor,
                    'cover_start_date' => $policy->cover_start_date?->toDateString(),
                    'cover_end_date' => $policy->cover_end_date?->toDateString(),
                    'next_premium_due_date' => $policy->next_premium_due_date?->toDateString(),
                    'enrolled_at' => $policy->enrolled_at?->toIso8601String(),
                    'issued_at' => $policy->issued_at?->toIso8601String(),
                    'product' => $product === null ? null : [
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'insurer_name' => $product->insurer_name,
                        'underwriter_name' => $product->underwriter_name,
                        'currency' => $product->currency,
                        'product_type' => $product->product_type,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'compass' => $this->wellbeing->compass($user, $currency, $asOf),
            'credit' => $credit,
            'policies' => $policies,
            'spaces' => $spaces,
            'availability' => [
                'credit' => $credit !== [],
                'protection' => $policies !== [],
                'spaces' => $spaces !== [],
            ],
            'freshness' => [
                'observed_at' => $asOf->toIso8601String(),
                'window_seconds' => 300,
                'server_authoritative' => true,
            ],
        ];
    }
}
