<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MobileHomeService
{
    public function __construct(
        private readonly FinancialWellbeingService $wellbeing,
        private readonly CustomerCreditProfileService $creditProfiles,
        private readonly ProtectionService $protection,
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
        $policies = $this->protection->policiesFor($user)->toArray();

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
