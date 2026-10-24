<?php

namespace App\Services;

use InvalidArgumentException;

/** Portfolio-only deterministic scenario calculator; never an approval input. */
final class ClimateScenarioEngine
{
    public function simulate(array $portfolio, array $scenario): array
    {
        foreach (['outstanding_minor', 'baseline_par30_rate', 'baseline_default_rate'] as $key) {
            if (! isset($portfolio[$key]) || ! is_numeric($portfolio[$key]) || $portfolio[$key] < 0) {
                throw new InvalidArgumentException("Invalid portfolio input: {$key}");
            }
        }
        foreach (['par30_multiplier', 'default_multiplier'] as $key) {
            if (! isset($scenario[$key]) || ! is_numeric($scenario[$key]) || $scenario[$key] < 0) {
                throw new InvalidArgumentException("Invalid scenario input: {$key}");
            }
        }
        foreach (['baseline_par30_rate', 'baseline_default_rate'] as $key) {
            if ($portfolio[$key] > 1) {
                throw new InvalidArgumentException("Rate must be between zero and one: {$key}");
            }
        }
        $exposure = (int) $portfolio['outstanding_minor'];
        $par30 = min(1.0, (float) $portfolio['baseline_par30_rate'] * (float) $scenario['par30_multiplier']);
        $default = min(1.0, (float) $portfolio['baseline_default_rate'] * (float) $scenario['default_multiplier']);
        return [
            'mode' => 'scenario_only',
            'production_decision_eligible' => false,
            'outstanding_minor' => $exposure,
            'stressed_par30_rate' => $par30,
            'stressed_default_rate' => $default,
            'stressed_par30_minor' => (int) round($exposure * $par30),
            'stressed_default_minor' => (int) round($exposure * $default),
            'scenario_version' => (string) ($scenario['version'] ?? 'unvalidated'),
        ];
    }
}
