<?php

namespace App\Services;

use App\Models\LoanApplication;
use InvalidArgumentException;

class AppStoreCreditPolicy
{
    public const MAX_APR_PERCENT = 36.0;
    public const MIN_FULL_REPAYMENT_DAYS = 61;

    public function validateOffer(LoanApplication $application, array $pricing): array
    {
        if (($application->distribution_channel ?? 'web') !== 'app_store') {
            return [];
        }

        $application->loadMissing(['loanProductTerm', 'creditDecision']);
        $term = $application->loanProductTerm;
        $decision = $application->creditDecision;

        if (! $term || ! $decision || $decision->approved_amount_minor <= 0) {
            throw new InvalidArgumentException('An approved credit decision and product term are required before App Store loan compliance can be evaluated.');
        }

        $durationDays = (int) $term->duration;
        if ($durationDays < self::MIN_FULL_REPAYMENT_DAYS) {
            throw new InvalidArgumentException('This credit product cannot be offered through the iOS App Store because full repayment would be required in 60 days or less.');
        }

        $principal = (int) $decision->approved_amount_minor;
        $ratePercent = (float) $term->interest_rate;
        $cycleDays = $this->cycleDays((string) $term->interest_cycle);
        $termRatePercent = ($ratePercent / $cycleDays) * $durationDays;
        $interest = (int) round($principal * ($termRatePercent / 100));
        $fees = (int) ($pricing['access_fee_minor'] ?? 0) + (int) ($pricing['disbursement_fee_minor'] ?? 0);
        $feeTreatment = (string) ($pricing['fee_treatment'] ?? 'financed');
        $netDisbursement = $feeTreatment === 'deducted' ? $principal - $fees : $principal;
        $totalRepayment = $principal + $interest + ($feeTreatment === 'financed' ? $fees : 0);

        if ($netDisbursement <= 0) {
            throw new InvalidArgumentException('The disclosed amount received must be positive before App Store loan compliance can be evaluated.');
        }

        $financeCharge = $totalRepayment - $netDisbursement;
        $equivalentApr = ($financeCharge / $netDisbursement) * (365 / $durationDays) * 100;
        if ($equivalentApr > self::MAX_APR_PERCENT + 0.000001) {
            throw new InvalidArgumentException('This credit product cannot be offered through the iOS App Store because its equivalent maximum APR, including fees, exceeds 36%.');
        }

        return [
            'equivalent_maximum_apr_percent' => round($equivalentApr, 6),
            'first_payment_due_days_after_disbursement' => $this->frequencyDays((string) $term->repayment_frequency),
            'full_repayment_due_days_after_disbursement' => $durationDays,
            'app_store_policy_version' => 'apple-personal-loan-v1',
        ];
    }

    private function cycleDays(string $cycle): int
    {
        return match (strtolower($cycle)) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            default => throw new InvalidArgumentException('Unsupported interest cycle for App Store credit disclosure.'),
        };
    }

    private function frequencyDays(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'daily' => 1,
            'weekly' => 7,
            'fortnightly' => 14,
            'monthly' => 30,
            default => throw new InvalidArgumentException('Unsupported repayment frequency for App Store credit disclosure.'),
        };
    }
}
