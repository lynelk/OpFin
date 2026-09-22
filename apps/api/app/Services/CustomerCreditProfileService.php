<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\CreditProfile;
use App\Models\CreditScoreComponent;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
use App\Models\KycCase;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerCreditProfileService
{
    public function __construct(
        private readonly ExternalScoringService $externalScoring,
        private readonly PositiveEmploymentBehaviourService $employmentBehaviour,
    ) {}

    public function ensurePrimaryPhone(User $user): CustomerPhoneNumber
    {
        $phone = CustomerPhoneNumber::firstOrCreate(
            ['phone' => $user->phone],
            [
                'user_id' => $user->id,
                'kind' => 'primary',
                'verified_at' => $user->phone_verified_at ?? now(),
                'ownership_name_match_status' => 'account_verified',
            ],
        );

        if ((int) $phone->user_id === (int) $user->id && $phone->verified_at === null && $user->phone_verified_at) {
            $phone->update(['verified_at' => $user->phone_verified_at]);
        }

        CustomerWallet::firstOrCreate(
            ['user_id' => $user->id, 'provider' => 'mobile_money', 'msisdn' => $user->phone],
            [
                'phone_number_id' => $phone->id,
                'status' => 'active',
                'verified_at' => $phone->verified_at,
                'is_default_disbursement' => true,
                'is_default_repayment' => true,
            ],
        );

        return $phone->fresh();
    }

    public function refresh(User $user, bool $fetchExternal = true): CreditProfile
    {
        $this->ensurePrimaryPhone($user);

        $kyc = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('reviewed_at')
            ->first();

        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $kyc || ! $consent) {
            return $this->persistIncompleteProfile($user, $kyc !== null, $consent !== null);
        }

        $this->writeInternalComponent($user);
        if ($fetchExternal) {
            $this->externalScoring->refresh($user);
        }

        $components = CreditScoreComponent::query()
            ->where('user_id', $user->id)
            ->where('status', CreditScoreComponent::STATUS_READY)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->groupBy('source')
            ->map(fn ($items) => $items->sortByDesc('received_at')->first())
            ->values();

        $crb = $components->firstWhere('source', 'crb');
        $internal = $components->firstWhere('source', 'internal');
        $coverage = round((float) $components->sum('weight_percent'), 2);

        if (! $crb || ! $internal) {
            return CreditProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => CreditProfile::STATUS_PENDING,
                    'coverage_percent' => $coverage,
                    'credit_limit_minor' => 0,
                    'available_to_borrow_minor' => 0,
                    'current_exposure_minor' => $this->totalOutstanding($user),
                    'amount_due_minor' => $this->amountDue($user),
                    'total_outstanding_minor' => $this->totalOutstanding($user),
                    'next_due_date' => $this->nextDueDate($user),
                    'model_version' => (string) config('opfin.credit.model_version', 'composite-v1'),
                    'component_breakdown' => $this->breakdown($components),
                    'reason_codes' => ['CORE_SCORING_SOURCE_PENDING'],
                    'customer_explanations' => ['We are still checking your credit information.'],
                    'scored_at' => now(),
                    'expires_at' => now()->addDays(7),
                ],
            );
        }

        $weighted = 0.0;
        $weightReady = 0.0;
        foreach ($components as $component) {
            $weight = (float) $component->weight_percent;
            $weighted += ((float) $component->score) * $weight;
            $weightReady += $weight;
        }
        $baseComposite = $weightReady > 0 ? round($weighted / $weightReady, 2) : 0.0;
        $employmentBenefit = $this->employmentBehaviour->assess($user);
        $composite = min(100, round($baseComposite + (float) $employmentBenefit['uplift_points'], 2));
        $componentBreakdown = $this->breakdown($components);
        $componentBreakdown['positive_employment_behaviour'] = [
            'base_composite_score' => $baseComposite,
            ...$employmentBenefit,
        ];

        $latestCrb = CrbReport::query()->where('user_id', $user->id)->latest('received_at')->first();
        $adverse = $latestCrb?->status === CrbReport::STATUS_ADVERSE;
        [$band, $baseLimit] = $this->limitForScore($composite);
        $minimumCoverage = (float) config('opfin.credit.minimum_limit_coverage_percent', 60);
        $coverageFactor = min(1.0, $coverage / 100);
        $limit = $adverse || $coverage < $minimumCoverage ? 0 : (int) floor($baseLimit * $coverageFactor);
        $outstanding = $this->totalOutstanding($user);
        $hasActiveLoan = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->exists();
        $available = $hasActiveLoan ? 0 : max(0, $limit - $outstanding);

        $secondaryVerified = CustomerPhoneNumber::query()
            ->where('user_id', $user->id)
            ->where('kind', 'secondary')
            ->whereNotNull('verified_at')
            ->exists();

        $explanations = [
            'Your identity and primary phone are verified.',
            $secondaryVerified
                ? 'Your second phone is helping strengthen your profile.'
                : 'Adding a second phone is optional and may strengthen your profile.',
        ];
        if ($components->firstWhere('source', 'mno')) {
            $explanations[] = 'Verified mobile activity is included in your score.';
        }
        if ($components->firstWhere('source', 'third_party')) {
            $explanations[] = 'Approved partner information is included in your score.';
        }
        if ((float) $employmentBenefit['uplift_points'] > 0) {
            $explanations[] = 'Verified positive workplace information has provided a small capped uplift. Missing workplace information does not reduce your score.';
        }

        return CreditProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => $adverse
                    ? CreditProfile::STATUS_DECLINED
                    : ($coverage >= 100 ? CreditProfile::STATUS_READY : CreditProfile::STATUS_PROVISIONAL),
                'composite_score' => $composite,
                'band' => $band,
                'coverage_percent' => $coverage,
                'credit_limit_minor' => $limit,
                'current_exposure_minor' => $outstanding,
                'available_to_borrow_minor' => $available,
                'amount_due_minor' => $this->amountDue($user),
                'total_outstanding_minor' => $outstanding,
                'next_due_date' => $this->nextDueDate($user),
                'model_version' => (string) config('opfin.credit.model_version', 'composite-v1'),
                'component_breakdown' => $componentBreakdown,
                'reason_codes' => array_values(array_unique(array_filter([
                    'IDENTITY_VERIFIED',
                    'PRIMARY_PHONE_VERIFIED',
                    $secondaryVerified ? 'SECONDARY_PHONE_VERIFIED' : null,
                    $coverage < 100 ? 'PARTIAL_EXTERNAL_DATA_COVERAGE' : 'FULL_SCORING_COVERAGE',
                    $adverse ? 'CRB_ADVERSE_HISTORY' : 'CRB_ACCEPTABLE',
                    $hasActiveLoan ? 'ACTIVE_LOAN_MUST_BE_CLEARED' : null,
                    ...($employmentBenefit['reason_codes'] ?? []),
                ]))),
                'customer_explanations' => $explanations,
                'scored_at' => now(),
                'expires_at' => now()->addDays(30),
            ],
        );
    }

    public function status(User $user): array
    {
        $this->ensurePrimaryPhone($user);
        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $kyc = KycCase::query()->where('user_id', $user->id)->latest()->first();
        $secondary = CustomerPhoneNumber::query()
            ->where('user_id', $user->id)
            ->where('kind', 'secondary')
            ->whereNotNull('verified_at')
            ->exists();
        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->exists();

        $activeLoan = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->latest()
            ->first();

        return [
            'profile' => $profile,
            'active_loan' => $activeLoan ? [
                'id' => $activeLoan->id,
                'status' => $activeLoan->status,
                'outstanding_minor' => (int) $activeLoan->outstanding_balance,
            ] : null,
            'setup' => [
                'primary_phone_verified' => $user->phone_verified_at !== null,
                'secondary_phone_verified' => $secondary,
                'secondary_phone_required' => false,
                'kyc_status' => $kyc?->status ?? 'not_started',
                'kyc_evidence_complete' => $kyc?->evidence_complete_at !== null,
                'credit_consent_granted' => $consent,
            ],
            'next_action' => $this->nextAction($user, $kyc, $consent, $profile),
        ];
    }

    private function persistIncompleteProfile(User $user, bool $kycVerified, bool $consent): CreditProfile
    {
        $reasons = [];
        $messages = [];
        if (! $kycVerified) {
            $reasons[] = 'KYC_REQUIRED';
            $messages[] = 'Verify your National ID to see your credit limit.';
        }
        if (! $consent) {
            $reasons[] = 'CREDIT_CONSENT_REQUIRED';
            $messages[] = 'Allow OpFin to check your credit information before we calculate a limit.';
        }

        $outstanding = $this->totalOutstanding($user);

        return CreditProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => CreditProfile::STATUS_PENDING,
                'coverage_percent' => 0,
                'credit_limit_minor' => 0,
                'current_exposure_minor' => $outstanding,
                'available_to_borrow_minor' => 0,
                'amount_due_minor' => $this->amountDue($user),
                'total_outstanding_minor' => $outstanding,
                'next_due_date' => $this->nextDueDate($user),
                'model_version' => (string) config('opfin.credit.model_version', 'composite-v1'),
                'component_breakdown' => [],
                'reason_codes' => $reasons,
                'customer_explanations' => $messages,
                'scored_at' => null,
                'expires_at' => null,
            ],
        );
    }

    private function writeInternalComponent(User $user): void
    {
        $score = 0;
        $reasons = [];

        $kycVerified = KycCase::query()
            ->where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->exists();
        if ($kycVerified) {
            $score += 30;
            $reasons[] = 'IDENTITY_VERIFIED';
        }

        $primary = CustomerPhoneNumber::query()
            ->where('user_id', $user->id)
            ->where('kind', 'primary')
            ->whereNotNull('verified_at')
            ->exists();
        if ($primary) {
            $score += 20;
            $reasons[] = 'PRIMARY_PHONE_VERIFIED';
        }

        $secondary = CustomerPhoneNumber::query()
            ->where('user_id', $user->id)
            ->where('kind', 'secondary')
            ->whereNotNull('verified_at')
            ->exists();
        if ($secondary) {
            $score += 10;
            $reasons[] = 'SECONDARY_PHONE_VERIFIED';
        }

        $accountDays = max(0, (int) $user->created_at?->diffInDays(now()));
        $tenurePoints = min(10, (int) floor($accountDays / 30));
        $score += $tenurePoints;
        if ($tenurePoints > 0) {
            $reasons[] = 'ACCOUNT_TENURE';
        }

        $repaidLoans = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('status', 'Cleared')
            ->count();
        $repaymentPoints = $repaidLoans === 0 ? 15 : min(30, 15 + ($repaidLoans * 3));
        $score += $repaymentPoints;
        $reasons[] = $repaidLoans === 0 ? 'NEW_BORROWER_NEUTRAL_HISTORY' : 'POSITIVE_REPAYMENT_HISTORY';

        CreditScoreComponent::updateOrCreate(
            ['user_id' => $user->id, 'source' => 'internal', 'phone_number_id' => null],
            [
                'status' => CreditScoreComponent::STATUS_READY,
                'score' => min(100, $score),
                'weight_percent' => (float) config('opfin.credit.scoring_weights.internal', 20),
                'reason_codes' => $reasons,
                'raw_payload' => [
                    'verified_secondary_phone' => $secondary,
                    'account_tenure_days' => $accountDays,
                    'cleared_loans' => $repaidLoans,
                ],
                'received_at' => now(),
                'expires_at' => now()->addDays(7),
            ],
        );
    }

    private function limitForScore(float $score): array
    {
        $bands = (array) config('opfin.credit.limit_bands');
        foreach ($bands as $band => $rule) {
            if ($score >= (float) ($rule['min_score'] ?? 0)) {
                return [$band, (int) ($rule['limit_minor'] ?? 0)];
            }
        }

        return ['Not eligible', 0];
    }

    private function totalOutstanding(User $user): int
    {
        $production = 0;
        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $production = (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->sum('schedule.total_outstanding_minor');
        }

        $legacy = 0;
        if (Schema::hasTable('loan_schedules')) {
            $legacy = (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.total_outstanding', '>', 0)
                ->sum('schedule.total_outstanding'));
        }

        return $production + $legacy;
    }

    private function amountDue(User $user): int
    {
        $today = now()->toDateString();
        $production = Schema::hasTable('credit_repayment_schedule_items')
            ? (int) DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '<=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->sum('schedule.total_outstanding_minor')
            : 0;

        $legacy = Schema::hasTable('loan_schedules')
            ? (int) round((float) DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '<=', $today)
                ->where('schedule.total_outstanding', '>', 0)
                ->sum('schedule.total_outstanding'))
            : 0;

        return $production + $legacy;
    }

    private function nextDueDate(User $user): ?string
    {
        $today = now()->toDateString();
        $dates = [];

        if (Schema::hasTable('credit_repayment_schedule_items')) {
            $date = DB::table('credit_repayment_schedule_items as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '>=', $today)
                ->where('schedule.total_outstanding_minor', '>', 0)
                ->orderBy('schedule.due_date')
                ->value('schedule.due_date');
            if ($date) {
                $dates[] = (string) $date;
            }
        }

        if (Schema::hasTable('loan_schedules')) {
            $date = DB::table('loan_schedules as schedule')
                ->join('loans', 'loans.id', '=', 'schedule.loan_id')
                ->where('loans.user_id', $user->id)
                ->whereNull('loans.deleted_at')
                ->where('schedule.due_date', '>=', $today)
                ->where('schedule.total_outstanding', '>', 0)
                ->orderBy('schedule.due_date')
                ->value('schedule.due_date');
            if ($date) {
                $dates[] = (string) $date;
            }
        }

        sort($dates);

        return $dates[0] ?? null;
    }

    private function breakdown($components): array
    {
        return $components->mapWithKeys(fn ($component) => [
            $component->source => [
                'score' => round((float) $component->score, 2),
                'weight_percent' => round((float) $component->weight_percent, 2),
                'reason_codes' => $component->reason_codes ?? [],
            ],
        ])->all();
    }

    private function nextAction(User $user, ?KycCase $kyc, bool $consent, ?CreditProfile $profile): array
    {
        if ($user->phone_verified_at === null) {
            return ['code' => 'VERIFY_PHONE', 'label' => 'Verify your phone'];
        }
        if (! $kyc || $kyc->status !== KycCase::STATUS_VERIFIED) {
            return ['code' => 'VERIFY_IDENTITY', 'label' => 'Verify your National ID'];
        }
        if (! $consent) {
            return ['code' => 'GRANT_CREDIT_CONSENT', 'label' => 'Allow a credit check'];
        }
        if (! $profile || $profile->status === CreditProfile::STATUS_PENDING) {
            return ['code' => 'CALCULATE_PROFILE', 'label' => 'Check your loan limit'];
        }
        $activeLoan = Loan::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cleared', 'Cancelled', 'Rejected', 'Reversed'])
            ->exists();

        if ($profile->amount_due_minor > 0 || $activeLoan) {
            return ['code' => 'REPAY', 'label' => $profile->amount_due_minor > 0 ? 'Repay amount due' : 'View your active loan'];
        }
        if ($profile->available_to_borrow_minor > 0) {
            return ['code' => 'BORROW', 'label' => 'Borrow'];
        }

        return ['code' => 'VIEW_PROFILE', 'label' => 'View your profile'];
    }
}
