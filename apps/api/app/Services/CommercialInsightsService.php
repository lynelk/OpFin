<?php

namespace App\Services;

use App\Models\KycCase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CommercialInsightsService
{
    public const COST_TYPES = [
        'acquisition',
        'kyc',
        'crb',
        'payment',
        'support',
        'funding',
        'collections',
        'programme_delivery',
        'other',
    ];

    public const ACQUISITION_CHANNELS = [
        'organic',
        'employer',
        'programme',
        'referral',
        'agent',
        'whatsapp',
        'ussd',
        'web',
        'app',
        'partner',
        'other',
    ];

    public function __construct(
        private readonly InclusiveFinanceService $inclusiveFinance,
    ) {}

    public function recordAttribution(User $subject, array $data, ?User $actor = null): array
    {
        $existing = DB::table('customer_acquisition_attributions')->where('user_id', $subject->id)->first();

        DB::table('customer_acquisition_attributions')->updateOrInsert(
            ['user_id' => $subject->id],
            [
                'acquisition_channel' => $data['acquisition_channel'],
                'source' => $data['source'] ?? null,
                'campaign' => $data['campaign'] ?? null,
                'programme_id' => $data['programme_id'] ?? null,
                'acquired_at' => $data['acquired_at'] ?? $subject->created_at ?? now(),
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
                'recorded_by' => $actor?->id,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ],
        );

        return [
            'user_id' => $subject->id,
            'acquisition_channel' => $data['acquisition_channel'],
            'source' => $data['source'] ?? null,
            'campaign' => $data['campaign'] ?? null,
            'programme_id' => $data['programme_id'] ?? null,
        ];
    }

    public function recordCost(array $data, User $actor): array
    {
        if (($data['source_reference'] ?? null) && DB::table('commercial_cost_events')
            ->where('cost_type', $data['cost_type'])
            ->where('source_reference', $data['source_reference'])
            ->exists()) {
            throw new InvalidArgumentException('This commercial cost source has already been recorded.');
        }

        $id = DB::table('commercial_cost_events')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'cost_type' => $data['cost_type'],
            'channel' => $data['channel'] ?? null,
            'programme_id' => $data['programme_id'] ?? null,
            'amount_minor' => $data['amount_minor'],
            'currency' => strtoupper($data['currency'] ?? 'UGX'),
            'quantity' => $data['quantity'] ?? 1,
            'source_reference' => $data['source_reference'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'recorded_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'cost_type' => $data['cost_type'],
            'amount_minor' => (int) $data['amount_minor'],
            'currency' => strtoupper($data['currency'] ?? 'UGX'),
        ];
    }

    public function dashboard(?string $from = null, ?string $to = null, ?string $channel = null, ?int $programmeId = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->subDays(90)->startOfDay();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        if ($toDate->lt($fromDate)) {
            throw new InvalidArgumentException('Commercial reporting end date must be on or after the start date.');
        }

        $attributionQuery = DB::table('customer_acquisition_attributions')
            ->whereBetween('acquired_at', [$fromDate, $toDate]);
        if ($channel) {
            $attributionQuery->where('acquisition_channel', $channel);
        }
        if ($programmeId) {
            $attributionQuery->where('programme_id', $programmeId);
        }
        $attributions = $attributionQuery->get();
        $userIds = $attributions->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $costQuery = DB::table('commercial_cost_events')
            ->where('currency', 'UGX')
            ->whereBetween('occurred_at', [$fromDate, $toDate]);
        if ($channel) {
            $costQuery->where('channel', $channel);
        }
        if ($programmeId) {
            $costQuery->where('programme_id', $programmeId);
        }
        $costs = $costQuery->get();
        $costByType = $costs->groupBy('cost_type')->map(fn ($rows) => (int) $rows->sum('amount_minor'))->all();
        $totalCost = (int) $costs->sum('amount_minor');

        $applicationsQuery = DB::table('loan_applications')->whereBetween('created_at', [$fromDate, $toDate]);
        $decisionsQuery = DB::table('credit_decisions')->whereBetween('decided_at', [$fromDate, $toDate]);
        $loansQuery = DB::table('loans')->whereBetween('created_at', [$fromDate, $toDate]);
        $revenueQuery = DB::table('revenue_events')->whereBetween('occurred_at', [$fromDate, $toDate])->where('currency', 'UGX');

        if ($userIds !== []) {
            $applicationsQuery->whereIn('user_id', $userIds);
            $decisionsQuery->whereIn('user_id', $userIds);
            $loansQuery->whereIn('user_id', $userIds);
            $revenueQuery->whereIn('user_id', $userIds);
        } elseif ($channel || $programmeId) {
            $applicationsQuery->whereRaw('1 = 0');
            $decisionsQuery->whereRaw('1 = 0');
            $loansQuery->whereRaw('1 = 0');
            $revenueQuery->whereRaw('1 = 0');
        }

        $applications = $applicationsQuery->count();
        $decisions = $decisionsQuery->get();
        $approved = $decisions->where('status', 'approved')->count();
        $declined = $decisions->where('status', 'declined')->count();
        $referred = $decisions->where('status', 'referred')->count();

        $loans = $loansQuery->get();
        $disbursed = $loans->filter(fn ($loan) => $loan->disbursed_at !== null || in_array(strtolower((string) $loan->status), ['active', 'cleared', 'disbursed'], true));
        $disbursedCount = $disbursed->count();
        $principalDisbursed = (int) $disbursed->sum('amount');
        $npl = $loans->filter(fn ($loan) => $loan->non_performing_at !== null);
        $nplCount = $npl->count();
        $creditLossExposure = (int) $npl->sum(fn ($loan) => (int) ($loan->principal_at_npl_minor ?? 0));

        $revenue = (int) $revenueQuery->sum('opfin_amount_minor');
        $contributionBeforeCreditLoss = $revenue - $totalCost;
        $contributionAfterNplExposure = $contributionBeforeCreditLoss - $creditLossExposure;

        $acquiredCustomers = $attributions->pluck('user_id')->unique()->count();
        $acquisitionCost = (int) ($costByType['acquisition'] ?? 0);
        $cac = $acquiredCustomers > 0 ? (int) round($acquisitionCost / $acquiredCustomers) : null;

        $loanUserCounts = $loans->groupBy('user_id')->map->count();
        $repeatBorrowers = $loanUserCounts->filter(fn ($count) => $count >= 2)->count();
        $borrowers = $loanUserCounts->count();

        $overdueLoanCount = DB::table('credit_repayment_schedule_items as item')
            ->join('loans', 'loans.id', '=', 'item.loan_id')
            ->where('item.total_outstanding_minor', '>', 0)
            ->whereDate('item.due_date', '<', now()->toDateString())
            ->when($userIds !== [], fn ($query) => $query->whereIn('loans.user_id', $userIds))
            ->when(($channel || $programmeId) && $userIds === [], fn ($query) => $query->whereRaw('1 = 0'))
            ->distinct()
            ->count('item.loan_id');

        return [
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'currency' => 'UGX',
                'channel' => $channel,
                'programme_id' => $programmeId,
            ],
            'acquisition' => [
                'customers' => $acquiredCustomers,
                'acquisition_cost_minor' => $acquisitionCost,
                'cac_minor' => $cac,
                'by_channel' => $attributions->groupBy('acquisition_channel')->map(fn ($rows) => $rows->pluck('user_id')->unique()->count())->all(),
            ],
            'funnel' => [
                'applications' => $applications,
                'approved' => $approved,
                'declined' => $declined,
                'referred' => $referred,
                'approval_rate_percent' => $applications > 0 ? round(($approved / $applications) * 100, 1) : null,
                'disbursed_loans' => $disbursedCount,
                'principal_disbursed_minor' => $principalDisbursed,
            ],
            'portfolio' => [
                'borrowers' => $borrowers,
                'repeat_borrowers' => $repeatBorrowers,
                'repeat_rate_percent' => $borrowers > 0 ? round(($repeatBorrowers / $borrowers) * 100, 1) : null,
                'npl_count' => $nplCount,
                'npl_rate_percent' => $disbursedCount > 0 ? round(($nplCount / $disbursedCount) * 100, 1) : null,
                'overdue_loan_count' => $overdueLoanCount,
                'npl_principal_exposure_minor' => $creditLossExposure,
            ],
            'economics' => [
                'opfin_revenue_minor' => $revenue,
                'recorded_cost_minor' => $totalCost,
                'cost_by_type' => $costByType,
                'contribution_before_credit_loss_minor' => $contributionBeforeCreditLoss,
                'contribution_after_npl_exposure_minor' => $contributionAfterNplExposure,
                'revenue_per_acquired_customer_minor' => $acquiredCustomers > 0 ? (int) round($revenue / $acquiredCustomers) : null,
            ],
            'cohorts' => $this->cohortSummary($attributions, $fromDate, $toDate),
            'measurement_notes' => [
                'cost_truth' => 'Commercial costs are counted only when recorded as governed commercial cost events.',
                'credit_loss' => 'Contribution after credit loss uses recorded principal-at-NPL exposure, not an unverified expected-loss model.',
                'cac' => 'CAC uses attributed acquired customers and recorded acquisition cost. Missing marketing cost data produces an incomplete CAC rather than an invented estimate.',
            ],
        ];
    }

    public function evaluateGraduations(?int $programmeId = null): array
    {
        $enrolments = DB::table('inclusive_finance_enrolments')
            ->when($programmeId, fn ($query) => $query->where('programme_id', $programmeId))
            ->get();

        $evaluated = 0;
        $graduated = 0;

        foreach ($enrolments as $enrolment) {
            $user = User::withoutGlobalScopes()->find($enrolment->user_id);
            if (! $user) {
                continue;
            }

            $criteria = $this->graduationCriteria($user, $enrolment);
            $isReady = collect($criteria)->every(fn ($value) => $value === true);
            $existing = DB::table('programme_commercial_graduations')->where('enrolment_id', $enrolment->id)->first();
            $graduatedAt = $isReady ? ($existing?->graduated_at ?? now()) : null;

            DB::table('programme_commercial_graduations')->updateOrInsert(
                ['enrolment_id' => $enrolment->id],
                [
                    'programme_id' => $enrolment->programme_id,
                    'user_id' => $user->id,
                    'status' => $isReady ? 'graduated' : 'not_ready',
                    'criteria' => json_encode($criteria),
                    'reason_codes' => json_encode(array_keys(array_filter($criteria, fn ($value) => $value === false))),
                    'evaluated_at' => now(),
                    'graduated_at' => $graduatedAt,
                    'created_at' => $existing?->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );

            $evaluated++;
            if ($isReady) {
                $graduated++;
            }
        }

        return ['evaluated' => $evaluated, 'graduated' => $graduated];
    }

    public function graduationSummary(?int $programmeId = null): array
    {
        $this->evaluateGraduations($programmeId);

        $rows = DB::table('programme_commercial_graduations')
            ->when($programmeId, fn ($query) => $query->where('programme_id', $programmeId))
            ->get();

        $graduated = $rows->where('status', 'graduated')->count();
        $total = $rows->count();

        return [
            'programme_id' => $programmeId,
            'evaluated_participants' => $total,
            'graduated_participants' => $graduated,
            'graduation_rate_percent' => $total > 0 ? round(($graduated / $total) * 100, 1) : null,
            'criteria' => [
                'verified_identity',
                'established_financial_reputation',
                'two_or_more_cleared_loans',
                'no_recorded_npl',
                'not_dependent_on_active_development_guarantee',
            ],
            'boundary' => 'Commercial graduation is an analytics outcome. It does not approve credit, change pricing or replace underwriting.',
        ];
    }

    private function graduationCriteria(User $user, object $enrolment): array
    {
        $verifiedKyc = KycCase::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->exists();

        $reputation = $this->inclusiveFinance->reputation($user);
        $clearedLoans = DB::table('loans')
            ->where('user_id', $user->id)
            ->whereRaw('LOWER(status) = ?', ['cleared'])
            ->count();

        $hasNpl = DB::table('loans')
            ->where('user_id', $user->id)
            ->whereNotNull('non_performing_at')
            ->exists();

        $activeDevelopmentGuarantee = DB::table('credit_support_instruments')
            ->where('user_id', $user->id)
            ->where('instrument_type', 'development_guarantee')
            ->where('verification_status', 'verified')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        return [
            'verified_identity' => $verifiedKyc,
            'established_financial_reputation' => ($reputation['stage'] ?? null) === 'established',
            'two_or_more_cleared_loans' => $clearedLoans >= 2,
            'no_recorded_npl' => ! $hasNpl,
            'not_dependent_on_active_development_guarantee' => ! $activeDevelopmentGuarantee,
        ];
    }

    private function cohortSummary($attributions, Carbon $fromDate, Carbon $toDate): array
    {
        return $attributions
            ->groupBy(fn ($row) => Carbon::parse($row->acquired_at)->format('Y-m'))
            ->map(function ($rows, $month) use ($toDate) {
                $userIds = $rows->pluck('user_id')->unique()->all();
                $loanCount = $userIds === [] ? 0 : DB::table('loans')->whereIn('user_id', $userIds)->where('created_at', '<=', $toDate)->count();
                $repeat = $userIds === [] ? 0 : DB::table('loans')
                    ->whereIn('user_id', $userIds)
                    ->where('created_at', '<=', $toDate)
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) >= 2')
                    ->get()
                    ->count();

                return [
                    'cohort' => $month,
                    'customers' => count($userIds),
                    'loans_to_date' => $loanCount,
                    'repeat_borrowers_to_date' => $repeat,
                ];
            })
            ->values()
            ->all();
    }
}
