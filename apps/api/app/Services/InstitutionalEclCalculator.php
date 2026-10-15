<?php

namespace App\Services;

use InvalidArgumentException;

final class InstitutionalEclCalculator
{
    public const MODEL_VERSION = 'weighted-pd-lgd-v1';

    /**
     * Scenario fields: weight_bps, pd_12m_bps, pd_lifetime_bps, pd_impaired_bps, lgd_bps.
     * Assumptions must be approved by the reporting institution; no regulator rates are inferred.
     */
    public function validateScenarios(array $scenarios): void
    {
        if (count($scenarios) < 2 || count($scenarios) > 5) {
            throw new InvalidArgumentException('Use two to five forward-looking scenarios.');
        }
        $weights = 0;
        foreach ($scenarios as $scenario) {
            if (! is_array($scenario)) {
                throw new InvalidArgumentException('Invalid ECL scenario.');
            }
            foreach (['weight_bps', 'pd_12m_bps', 'pd_lifetime_bps', 'pd_impaired_bps', 'lgd_bps'] as $field) {
                if (! isset($scenario[$field]) || ! is_int($scenario[$field]) || $scenario[$field] < 0 || $scenario[$field] > 10000) {
                    throw new InvalidArgumentException('Each ECL scenario needs integer basis points: '.$field);
                }
            }
            if ($scenario['pd_lifetime_bps'] < $scenario['pd_12m_bps']) {
                throw new InvalidArgumentException('Lifetime PD cannot be less than 12-month PD.');
            }
            $weights += $scenario['weight_bps'];
        }
        if ($weights !== 10000) {
            throw new InvalidArgumentException('ECL scenario weights must total 10,000 basis points.');
        }
    }

    public function calculate(
        int $grossMinor,
        int $dpd,
        bool $sicr,
        bool $impaired,
        int $monthsRemaining,
        int $stage2Dpd,
        int $stage3Dpd,
        int $discountBps,
        array $scenarios
    ): array {
        $this->validateScenarios($scenarios);
        if ($grossMinor < 0 || $grossMinor > 1000000000000 || $dpd < 0 || $monthsRemaining < 0
            || $monthsRemaining > 600 || $stage2Dpd < 1 || $stage3Dpd <= $stage2Dpd
            || $discountBps < 0 || $discountBps > 10000) {
            throw new InvalidArgumentException('ECL exposure, stage thresholds or discount input is invalid.');
        }
        $stage = $impaired || $dpd >= $stage3Dpd ? 'stage_3'
            : ($sicr || $dpd >= $stage2Dpd ? 'stage_2' : 'stage_1');
        $field = match ($stage) {
            'stage_1' => 'pd_12m_bps',
            'stage_2' => 'pd_lifetime_bps',
            default => 'pd_impaired_bps',
        };
        // This is an approved, scenario-weighted simplified ECL model, not a generic IFRS 9 certification.
        // Discounted using the contractual remaining term proxy. Entity validation is still mandatory.
        $years = $monthsRemaining / 12;
        $discountFactor = pow(1 + $discountBps / 10000, $years);
        $expected = 0.0;
        foreach ($scenarios as $scenario) {
            $expected += $grossMinor
                * ($scenario[$field] / 10000)
                * ($scenario['lgd_bps'] / 10000)
                * ($scenario['weight_bps'] / 10000)
                / $discountFactor;
        }
        $ecl = min($grossMinor, (int) round($expected));
        return [
            'stage' => $stage,
            'expected_credit_loss_minor' => $ecl,
            'evidence' => [
                'model_version' => self::MODEL_VERSION,
                'stage_basis' => ['days_past_due' => $dpd, 'significant_increase_in_credit_risk' => $sicr, 'credit_impaired' => $impaired],
                'months_remaining' => $monthsRemaining,
                'pd_horizon' => $stage === 'stage_1' ? '12_month_default_events' : 'lifetime_default_events',
                'discount_bps' => $discountBps,
                'scenario_weights_bps' => array_column($scenarios, 'weight_bps'),
            ],
        ];
    }
}
