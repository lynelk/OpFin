<?php

namespace App\Services;

use App\Models\LoanApplication;
use InvalidArgumentException;

class AppStoreCreditPolicy
{
    /** `android` remains protected for clients released before `play_store` was introduced. */
    public const STORE_CHANNELS = ['android', 'app_store', 'play_store'];

    public const MAX_APR_PERCENT = 36.0;

    public const MIN_FULL_REPAYMENT_DAYS = 61;

    public const PREFERRED_FULL_REPAYMENT_DAYS = 90;

    public function __construct(private readonly CreditCashFlowService $cashFlows) {}

    public function validateOffer(LoanApplication $application, array $pricing): array
    {
        if (! $this->isStoreChannel((string) ($application->distribution_channel ?? 'web'))) {
            return [];
        }

        $application->loadMissing(['loanProductTerm', 'creditDecision']);
        $term = $application->loanProductTerm;
        $decision = $application->creditDecision;

        if (! $term || ! $decision || $decision->approved_amount_minor <= 0) {
            throw new InvalidArgumentException('An approved credit decision and product term are required before mobile-store loan compliance can be evaluated.');
        }

        $durationDays = (int) $term->duration;
        if ($durationDays < self::MIN_FULL_REPAYMENT_DAYS) {
            throw new InvalidArgumentException('This credit product cannot be offered through a mobile app store because full repayment would be required in 60 days or less.');
        }

        $principal = (int) $decision->approved_amount_minor;
        $ratePercent = (float) $term->interest_rate;
        $cycleDays = $this->cycleDays((string) $term->interest_cycle);
        $termRatePercent = ($ratePercent / $cycleDays) * $durationDays;
        $interest = (int) round($principal * ($termRatePercent / 100));
        $fees = (int) ($pricing['access_fee_minor'] ?? 0) + (int) ($pricing['disbursement_fee_minor'] ?? 0);
        $feeTreatment = (string) ($pricing['fee_treatment'] ?? 'financed');

        if (! in_array($feeTreatment, ['financed', 'deducted'], true)) {
            throw new InvalidArgumentException('Fee treatment must be financed or deducted.');
        }
        if ($fees < 0) {
            throw new InvalidArgumentException('Credit fees cannot be negative.');
        }

        $netDisbursement = $feeTreatment === 'deducted' ? $principal - $fees : $principal;
        $repayableFees = $feeTreatment === 'financed' ? $fees : 0;

        if ($netDisbursement <= 0) {
            throw new InvalidArgumentException('The disclosed amount received must be positive before mobile-store loan compliance can be evaluated.');
        }

        $schedule = $this->cashFlows->repaymentSchedule(
            $principal,
            $interest,
            $repayableFees,
            $durationDays,
            (string) $term->repayment_frequency,
        );
        $equivalentApr = $this->cashFlows->equivalentAnnualPercentageRate($netDisbursement, $schedule);

        if (($application->distribution_channel ?? 'web') === 'app_store'
            && $equivalentApr > self::MAX_APR_PERCENT + 0.000001) {
            throw new InvalidArgumentException('This credit product cannot be offered through the iOS App Store because its equivalent maximum APR, including fees, exceeds 36%.');
        }

        return [
            'equivalent_maximum_apr_percent' => round($equivalentApr, 6),
            'apr_calculation_method' => CreditCashFlowService::APR_ALGORITHM_VERSION,
            'schedule_calculation_method' => CreditCashFlowService::SCHEDULE_ALGORITHM_VERSION,
            'first_payment_due_days_after_disbursement' => $schedule[0]['due_offset_days'],
            'full_repayment_due_days_after_disbursement' => $durationDays,
            'mobile_store_policy_version' => 'personal-loan-store-v3',
            'distribution_channel' => (string) $application->distribution_channel,
        ];
    }

    public function isStoreChannel(string $channel): bool
    {
        return in_array($channel, self::STORE_CHANNELS, true);
    }

    private function cycleDays(string $cycle): int
    {
        return match (strtolower($cycle)) {
            'daily' => 1,
            'weekly' => 7,
            'monthly' => 30,
            default => throw new InvalidArgumentException('Unsupported interest cycle for mobile-store credit disclosure.'),
        };
    }
}
