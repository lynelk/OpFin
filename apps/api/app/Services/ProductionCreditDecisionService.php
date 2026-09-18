<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\CreditDecision;
use App\Models\CreditProfile;
use App\Models\KycCase;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProductionCreditDecisionService
{
    public function __construct(
        private readonly AffordabilityService $affordability,
        private readonly GuarantorService $guarantors,
    ) {}

    public function decide(LoanApplication $application, ?User $actor = null): CreditDecision
    {
        $application->loadMissing(['user', 'loanProductTerm']);
        $user = $application->user;

        try {
            $this->guarantors->assertRequirementSatisfied($application);
        } catch (\InvalidArgumentException $exception) {
            return $this->record(
                $application,
                $actor,
                CreditDecision::STATUS_REFERRED,
                0,
                ['GUARANTOR_VERIFICATION_REQUIRED'],
                $exception->getMessage(),
            );
        }

        $kyc = KycCase::where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('reviewed_at')
            ->first();

        if (! $kyc) {
            return $this->record(
                $application, $actor, CreditDecision::STATUS_REFERRED, 0,
                ['KYC_VERIFICATION_REQUIRED'],
                'Verified identity is required before credit decisioning.',
            );
        }

        $consent = ConsentRecord::where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return $this->record(
                $application, $actor, CreditDecision::STATUS_REFERRED, 0,
                ['CONSENT_REQUIRED'],
                'Active credit-processing consent is required.',
            );
        }

        $crb = CrbReport::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('received_at')
            ->first();

        if (! $crb || $crb->status === CrbReport::STATUS_PENDING) {
            return $this->record(
                $application, $actor, CreditDecision::STATUS_REFERRED, 0,
                ['CRB_REPORT_REQUIRED'],
                'A current CRB result is required before automatic approval.',
            );
        }

        if ($crb->status === CrbReport::STATUS_ADVERSE) {
            return $this->record(
                $application, $actor, CreditDecision::STATUS_DECLINED, 0,
                ['CRB_ADVERSE_HISTORY'],
                'The application does not meet the current credit policy.',
                $crb,
            );
        }

        $profile = CreditProfile::query()->where('user_id', $user->id)->first();
        $amountMinor = (int) $application->amount;

        $withinProfile = $profile
            && in_array(
                $profile->status,
                [CreditProfile::STATUS_READY, CreditProfile::STATUS_PROVISIONAL],
                true,
            )
            && $profile->expires_at?->isFuture()
            && $profile->available_to_borrow_minor >= $amountMinor
            && $profile->credit_limit_minor > 0;

        if (! $withinProfile) {
            return $this->record(
                $application,
                $actor,
                CreditDecision::STATUS_REFERRED,
                min($amountMinor, (int) ($profile?->available_to_borrow_minor ?? 0)),
                ['KYC_VERIFIED', 'CONSENT_GRANTED', 'CRB_CLEAR', 'PROFILE_LIMIT_REVIEW_REQUIRED'],
                'The request needs review because a current automatic credit limit is not available or is insufficient.',
                $crb,
            );
        }

        $affordability = $this->affordability->assess($user, $application, $amountMinor);

        if ($affordability['status'] === 'unavailable') {
            return $this->record(
                $application,
                $actor,
                CreditDecision::STATUS_REFERRED,
                $amountMinor,
                [
                    'KYC_VERIFIED',
                    'CONSENT_GRANTED',
                    'CRB_CLEAR',
                    'WITHIN_PROFILE_CREDIT_LIMIT',
                    $affordability['reason_code'],
                ],
                'The request is within the profile limit but requires a verified affordability input before automatic approval.',
                $crb,
                (string) config('opfin.credit.auto_decision_policy_version', 'credit-profile-v1'),
            );
        }

        if ($affordability['status'] === 'ineligible') {
            return $this->record(
                $application,
                $actor,
                CreditDecision::STATUS_DECLINED,
                0,
                [
                    'KYC_VERIFIED',
                    'CONSENT_GRANTED',
                    'CRB_CLEAR',
                    'WITHIN_PROFILE_CREDIT_LIMIT',
                    $affordability['reason_code'],
                ],
                'The request does not meet the configured affordability limit.',
                $crb,
                (string) config('opfin.credit.auto_decision_policy_version', 'credit-profile-v1'),
                (int) $affordability['monthly_income_minor'],
                (int) $affordability['estimated_obligation_minor'],
            );
        }

        return $this->record(
            $application,
            $actor,
            CreditDecision::STATUS_APPROVED,
            $amountMinor,
            array_values(array_unique([
                'KYC_VERIFIED',
                'CONSENT_GRANTED',
                'CRB_CLEAR',
                'WITHIN_PROFILE_CREDIT_LIMIT',
                $affordability['reason_code'],
                ...($profile->reason_codes ?? []),
            ])),
            'Automatically approved within the current profile limit and affordability policy.',
            $crb,
            (string) config('opfin.credit.auto_decision_policy_version', 'credit-profile-v1'),
            (int) $affordability['monthly_income_minor'],
            (int) $affordability['estimated_obligation_minor'],
        );
    }

    private function record(
        LoanApplication $application,
        ?User $actor,
        string $status,
        int $approvedAmountMinor,
        array $reasonCodes,
        string $summary,
        ?CrbReport $crb = null,
        ?string $policyVersion = null,
        ?int $monthlyIncomeMinor = null,
        ?int $estimatedObligationMinor = null,
    ): CreditDecision {
        return CreditDecision::updateOrCreate(
            ['loan_application_id' => $application->id],
            [
                'user_id' => $application->user_id,
                'crb_report_id' => $crb?->id,
                'decided_by' => $actor?->id,
                'status' => $status,
                'requested_amount_minor' => (int) $application->amount,
                'approved_amount_minor' => $approvedAmountMinor,
                'monthly_income_minor' => $monthlyIncomeMinor,
                'estimated_obligation_minor' => $estimatedObligationMinor,
                'policy_version' => $policyVersion,
                'reason_codes' => $reasonCodes,
                'decision_summary' => $summary,
                'decided_at' => Carbon::now(),
            ]
        );
    }
}
