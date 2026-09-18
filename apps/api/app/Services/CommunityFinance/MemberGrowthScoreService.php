<?php

namespace App\Services\CommunityFinance;

class MemberGrowthScoreService
{
    /**
     * @param  array<string, array<string, int|float>>  $signals
     * @param  array<string, mixed>|null  $definition
     * @return array<string, mixed>
     */
    public function calculate(array $signals, ?array $definition = null): array
    {
        $definition ??= $this->defaultDefinition();
        $components = $definition['components'] ?? [];
        $totalComponentWeight = max(1, array_sum(array_map(fn ($component) => (float) ($component['weight'] ?? 0), $components)));

        $componentScores = [];
        $factorBreakdown = [];
        $weightedScoreTotal = 0.0;
        $weightedScoreWeight = 0.0;
        $confidenceTotal = 0.0;

        foreach ($components as $componentKey => $component) {
            $componentWeight = (float) ($component['weight'] ?? 0);
            $factors = $component['factors'] ?? [];
            $availableFactorWeight = 0.0;
            $availableWeightedScore = 0.0;
            $totalFactorWeight = max(1, array_sum(array_map(fn ($factor) => (float) ($factor['weight'] ?? 0), $factors)));
            $missing = [];
            $componentFactors = [];

            foreach ($factors as $factorKey => $factor) {
                $factorWeight = (float) ($factor['weight'] ?? 0);
                $raw = $signals[$componentKey][$factorKey] ?? null;

                if ($raw === null) {
                    $missing[] = $factorKey;
                    $componentFactors[$factorKey] = [
                        'label' => $factor['label'] ?? $factorKey,
                        'score' => null,
                        'weight' => $factorWeight,
                        'status' => 'missing',
                    ];
                    continue;
                }

                $score = $this->normaliseScore($raw);
                $availableFactorWeight += $factorWeight;
                $availableWeightedScore += $score * $factorWeight;
                $componentFactors[$factorKey] = [
                    'label' => $factor['label'] ?? $factorKey,
                    'score' => $score,
                    'weight' => $factorWeight,
                    'status' => 'available',
                ];
            }

            $coverage = (int) round(($availableFactorWeight / $totalFactorWeight) * 100);
            $componentScore = $availableFactorWeight > 0 ? (int) round($availableWeightedScore / $availableFactorWeight) : null;

            if ($componentScore !== null) {
                $weightedScoreTotal += $componentScore * $componentWeight;
                $weightedScoreWeight += $componentWeight;
            }

            $confidenceTotal += ($coverage / 100) * $componentWeight;

            $componentScores[$componentKey] = [
                'label' => $component['label'] ?? $componentKey,
                'score' => $componentScore,
                'weight' => $componentWeight,
                'coverage_percent' => $coverage,
                'missing_factors' => $missing,
            ];
            $factorBreakdown[$componentKey] = $componentFactors;
        }

        $compositeScore = $weightedScoreWeight > 0 ? (int) round($weightedScoreTotal / $weightedScoreWeight) : null;
        $confidence = (int) round(($confidenceTotal / $totalComponentWeight) * 100);

        return [
            'score_name' => $definition['public_component_name'] ?? 'Member Growth Score',
            'composite_name' => $definition['composite_name'] ?? 'OpFin Growth Passport',
            'policy_version' => $definition['policy_version'] ?? 'member-growth-score-v1.0-dormant',
            'composite_score' => $compositeScore,
            'band' => $this->bandFor($compositeScore, $definition['bands'] ?? []),
            'confidence_percent' => $confidence,
            'component_scores' => $componentScores,
            'factor_breakdown' => $factorBreakdown,
            'explanation' => [
                'plain_language' => 'The composite score is an aggregated view. Each component and factor remains visible so members, staff and reviewers can understand what helped, what hurt and what data was missing.',
                'missing_data_rule' => 'Missing factors are not silently converted into failures; they reduce confidence and remain visible in the breakdown.',
                'activation_state' => 'Dormant: do not use for automated adverse action, pricing increases or live credit decisions until the score policy and disclosures are approved.',
            ],
        ];
    }

    private function normaliseScore(int|float $score): int
    {
        return (int) max(0, min(100, round($score)));
    }

    /**
     * @param  array<string, array<string, mixed>>  $bands
     */
    private function bandFor(?int $score, array $bands): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach ($bands as $key => $band) {
            if ($score >= (int) ($band['min'] ?? 0)) {
                return (string) ($band['label'] ?? $key);
            }
        }

        return 'Rebuild Path';
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultDefinition(): array
    {
        return config('community_finance.score', [
            'policy_version' => 'member-growth-score-v1.0-dormant',
            'composite_name' => 'OpFin Growth Passport',
            'public_component_name' => 'Member Growth Score',
            'bands' => [
                'growth_leader' => ['min' => 85, 'label' => 'Growth Leader'],
                'steady_builder' => ['min' => 70, 'label' => 'Steady Builder'],
                'building_momentum' => ['min' => 55, 'label' => 'Building Momentum'],
                'needs_support' => ['min' => 40, 'label' => 'Needs Support'],
                'rebuild_path' => ['min' => 0, 'label' => 'Rebuild Path'],
            ],
            'components' => [
                'financial' => ['label' => 'Financial Habits', 'weight' => 40, 'factors' => ['savings_consistency' => ['label' => 'Savings consistency', 'weight' => 50], 'repayment_discipline' => ['label' => 'Repayment discipline', 'weight' => 50]]],
                'platform' => ['label' => 'Platform Trust', 'weight' => 20, 'factors' => ['verified_identity_depth' => ['label' => 'Verified identity depth', 'weight' => 100]]],
                'community' => ['label' => 'Community Participation', 'weight' => 20, 'factors' => ['guarantor_reliability' => ['label' => 'Guarantor reliability', 'weight' => 100]]],
                'protection_asset' => ['label' => 'Protection & Asset Readiness', 'weight' => 10, 'factors' => ['cover_continuity' => ['label' => 'Cover continuity', 'weight' => 100]]],
                'conduct' => ['label' => 'Responsible Use', 'weight' => 10, 'factors' => ['terms_adherence' => ['label' => 'Terms adherence', 'weight' => 100]]],
            ],
        ]);
    }
}
