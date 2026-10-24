<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\KycCase;
use App\Models\LoanApplication;

/**
 * Fail-closed prerequisite gate for automated credit decisions.
 * Does not introduce experimental features into production underwriting.
 */
final class CreditEvidenceGate
{
    public function check(LoanApplication $application): array
    {
        $userId = $application->user_id;
        $reasons = [];
        if (! KycCase::query()->where('user_id', $userId)
            ->where('status', KycCase::STATUS_VERIFIED)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists()) {
            $reasons[] = 'KYC_VERIFICATION_REQUIRED';
        }

        // Use the latest consent event, not an older grant superseded by revocation.
        $consent = ConsentRecord::query()->where('user_id', $userId)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        if (! $consent || $consent->status !== ConsentRecord::STATUS_GRANTED
            || $consent->revoked_at !== null || ! $consent->granted_at) {
            $reasons[] = 'CONSENT_REQUIRED';
        }

        $crb = CrbReport::query()->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('received_at')->first();
        if (! $crb || $crb->status === CrbReport::STATUS_PENDING) {
            $reasons[] = 'CRB_REPORT_REQUIRED';
        }
        return ['passed' => $reasons === [], 'reason_codes' => $reasons];
    }
}
