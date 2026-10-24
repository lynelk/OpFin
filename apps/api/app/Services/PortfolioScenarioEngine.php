<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Portfolio stress simulator. Factor multipliers are scenario assumptions,
 * not validated underwriting coefficients. No borrower decision writes.
 */
final class PortfolioScenarioEngine
{
    private const FACTORS = [
        'macroeconomic', 'employment', 'geographic',
        'transactions', 'concentration', 'fraud',
    ];

    public function simulate(array $portfolio, array $scenario): array
    {
        foreach (['outstanding_minor', 'baseline_par30_rate', 'baseline_default_rate'] as $key) {
            if (! isset($portfolio[$key]) || ! is_numeric($portfolio[$key]) || ! is_finite((float) $portfolio[$key]) || $portfolio[$key] < 0) {
                throw new InvalidArgumentException("Invalid portfolio input: {$key}");
            }
        }
        if ($portfolio['baseline_par30_rate'] > 1 || $portfolio['baseline_default_rate'] > 1) {
            throw new InvalidArgumentException('Baseline rates must be between zero and one.');
        }
        $multipliers = ['par30' => 1.0, 'default' => 1.0];
        $assumptions = [];
        foreach ($scenario['factors'] ?? [] as $name => $factor) {
            if (! in_array($name, self::FACTORS, true) || ! is_array($factor)) {
                throw new InvalidArgumentException("Unsupported scenario factor: {$name}");
            }
            foreach (['par30', 'default'] as $metric) {
                $value = $factor[$metric.'_multiplier'] ?? 1.0;
                if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0 || $value > 10) {
                    throw new InvalidArgumentException("Invalid {$name} {$metric} multiplier");
                }
                $multipliers[$metric] *= (float) $value;
            }
            $assumptions[$name] = $factor;
        }
        $exposure = (int) $portfolio['outstanding_minor'];
        $par30 = min(1.0, (float) $portfolio['baseline_par30_rate'] * $multipliers['par30']);
        $default = min(1.0, (float) $portfolio['baseline_default_rate'] * $multipliers['default']);
        return [
            'mode' => 'scenario_only',
            'production_decision_eligible' => false,
            'policy_effect' => 'none',
            'factors' => $assumptions,
            'outstanding_minor' => $exposure,
            'stressed_par30_rate' => $par30,
            'stressed_default_rate' => $default,
            'stressed_par30_minor' => (int) round($exposure * $par30),
            'stressed_default_minor' => (int) round($exposure * $default),
            'scenario_version' => (string) ($scenario['version'] ?? 'unvalidated'),
        ];
    }
}
