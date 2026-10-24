<?php

namespace Tests\Unit;

use App\Services\PortfolioScenarioEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PortfolioScenarioEngineTest extends TestCase
{
    public function test_combines_six_factors_without_production_policy_effect(): void
    {
        $factors = [];
        foreach (['macroeconomic', 'employment', 'geographic', 'transactions', 'concentration', 'fraud'] as $factor) {
            $factors[$factor] = ['par30_multiplier' => 1.1, 'default_multiplier' => 1.2];
        }
        $result = (new PortfolioScenarioEngine())->simulate(
            ['outstanding_minor' => 100000, 'baseline_par30_rate' => 0.1, 'baseline_default_rate' => 0.05],
            ['version' => 'scenario-v1', 'factors' => $factors],
        );
        self::assertSame('scenario_only', $result['mode']);
        self::assertFalse($result['production_decision_eligible']);
        self::assertSame('none', $result['policy_effect']);
        self::assertCount(6, $result['factors']);
        self::assertGreaterThan(10000, $result['stressed_par30_minor']);
    }

    public function test_rejects_unknown_factors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PortfolioScenarioEngine())->simulate(
            ['outstanding_minor' => 100, 'baseline_par30_rate' => 0.1, 'baseline_default_rate' => 0.1],
            ['factors' => ['unapproved' => ['default_multiplier' => 2]]],
        );
    }
}
