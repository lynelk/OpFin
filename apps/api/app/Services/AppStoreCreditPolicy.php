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

    public function __construct(private readonly CreditEconomicsService $economics) {}

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

        $quote = $this->economics->quoteForApplication(
            $application,
            (int) $decision->approved_amount_minor,
            $pricing,
        );

        $equivalentApr = (float) $quote['equivalent_apr_percent'];
        if (($application->distribution_channel ?? 'web') === 'app_store'
            && $equivalentApr > self::MAX_APR_PERCENT + 0.000001) {
            throw new InvalidArgumentException('This credit product cannot be offered through the iOS App Store because its equivalent maximum APR, including fees, exceeds 36%.');
        }

        return [
            'equivalent_maximum_apr_percent' => round($equivalentApr, 6),
            'apr_calculation_method' => $quote['apr_algorithm_version'],
            'schedule_calculation_method' => $quote['schedule_algorithm_version'],
            'first_payment_due_days_after_disbursement' => (int) $quote['schedule'][0]['due_offset_days'],
            'full_repayment_due_days_after_disbursement' => $durationDays,
            'mobile_store_policy_version' => 'personal-loan-store-v3',
            'distribution_channel' => (string) $application->distribution_channel,
        ];
    }

    public function isStoreChannel(string $channel): bool
    {
        return in_array($channel, self::STORE_CHANNELS, true);
    }
}
