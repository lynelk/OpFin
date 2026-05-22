<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\CreditDecision;
use App\Models\KycCase;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class ProductionCreditDecisionService
{
    public function decide(LoanApplication $application, ?User $actor = null): CreditDecision
    {
        $application->loadMissing('user');
        $user = $application->user;
        $reasonCodes = [];

        $kyc = KycCase::where('user_id', $user->id)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('reviewed_at')
            ->first();

        if (! $kyc) {
            return $this->record($application, $actor, CreditDecision::STATUS_REFERRED, 0, ['KYC_VERIFICATION_REQUIRED'], 'Application requires verified KYC before production decisioning.');
        }

        $consent = ConsentRecord::where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return $this->record($application, $actor, CreditDecision::STATUS_REFERRED, 0, ['CONSENT_REQUIRED'], 'Application requires active credit-processing consent.');
        }

        $crb = CrbReport::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('received_at')
            ->first();

        if (! $crb || $crb->status === CrbReport::STATUS_PENDING) {
            return $this->record($application, $actor, CreditDecision::STATUS_REFERRED, 0, ['CRB_REPORT_REQUIRED'], 'Application requires a current CRB report before approval.');
        }

        if ($crb->status === CrbReport::STATUS_ADVERSE) {
            return $this->record($application, $actor, CreditDecision::STATUS_DECLINED, 0, ['CRB_ADVERSE_HISTORY'], 'Application declined because the current CRB report contains adverse credit history.', $crb);
        }

        $amountMinor = (int) $application->amount;
        $reasonCodes[] = 'KYC_VERIFIED';
        $reasonCodes[] = 'CONSENT_GRANTED';
        $reasonCodes[] = 'CRB_CLEAR';

        return $this->record($application, $actor, CreditDecision::STATUS_REFERRED, $amountMinor, $reasonCodes, 'Application passed prerequisite gates and requires operations approval before offer creation.', $crb);
    }

    /**
     * @param  array<int, string>  $reasonCodes
     */
    private function record(
        LoanApplication $application,
        ?User $actor,
        string $status,
        int $approvedAmountMinor,
        array $reasonCodes,
        string $summary,
        ?CrbReport $crb = null
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
                'reason_codes' => $reasonCodes,
                'decision_summary' => $summary,
                'decided_at' => Carbon::now(),
            ]
        );
    }
}
