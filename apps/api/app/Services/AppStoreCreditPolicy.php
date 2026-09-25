<?php

namespace App\Services;

use App\Models\LoanApplication;
use InvalidArgumentException;

/** Compatibility facade; policy values live in configuration and dated rules. */
class AppStoreCreditPolicy
{
    public function __construct(
        private readonly CreditEconomicsService $economics,
        private readonly CreditDistributionService $distribution,
    ) {}

    public function validateOffer(LoanApplication $application, array $pricing): array
    {
        $application->loadMissing(['loanProduct.institution', 'loanProductTerm', 'creditDecision']);
        $term = $application->loanProductTerm;
        $decision = $application->creditDecision;
        if (! $application->loanProduct || ! $term || ! $decision || $decision->approved_amount_minor <= 0) {
            throw new InvalidArgumentException('An approved decision, lender product and term are required before offer distribution can be evaluated.');
        }
        $quote = $this->economics->quoteForApplication($application, (int) $decision->approved_amount_minor, $pricing);
        $distribution = $this->distribution->requireAvailable($application->loanProduct, $term, $application->distribution_channel ?? 'web', (float) $quote['equivalent_apr_percent']);

        return [
            'equivalent_maximum_apr_percent' => $quote['equivalent_apr_percent'],
            'apr_calculation_method' => $quote['apr_algorithm_version'],
            'schedule_calculation_method' => $quote['schedule_algorithm_version'],
            'first_payment_due_days_after_disbursement' => (int) $quote['schedule'][0]['due_offset_days'],
            'full_repayment_due_days_after_disbursement' => (int) $term->duration,
            'mobile_store_policy_version' => (string) ($distribution['policy']['version'] ?? 'review'),
            'distribution_channel' => $application->distribution_channel ?? 'web',
            'distribution_policy' => $distribution,
        ];
    }

    public function isStoreChannel(string $channel): bool
    {
        return in_array($channel, config('credit_distribution.store_channels', []), true);
    }
}
