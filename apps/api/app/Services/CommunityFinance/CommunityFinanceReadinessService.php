<?php

namespace App\Services\CommunityFinance;

use RuntimeException;

class CommunityFinanceReadinessService
{
    /**
     * @return array<string, mixed>
     */
    public function catalogue(): array
    {
        return [
            'activation' => config('community_finance.activation'),
            'language' => config('community_finance.language'),
            'modules' => config('community_finance.modules'),
            'score' => [
                'policy_version' => config('community_finance.score.policy_version'),
                'composite_name' => config('community_finance.score.composite_name'),
                'public_component_name' => config('community_finance.score.public_component_name'),
                'components' => collect(config('community_finance.score.components', []))
                    ->map(fn (array $component) => [
                        'label' => $component['label'],
                        'weight' => $component['weight'],
                        'factors' => collect($component['factors'])
                            ->map(fn (array $factor) => [
                                'label' => $factor['label'],
                                'weight' => $factor['weight'],
                            ])
                            ->toArray(),
                    ])
                    ->toArray(),
            ],
        ];
    }

    public function isDormant(): bool
    {
        return ! $this->isLiveEnabled();
    }

    public function isLiveEnabled(): bool
    {
        return (bool) config('community_finance.activation.live_enabled', false)
            && config('community_finance.activation.mode') === 'live'
            && (bool) config('community_finance.activation.sacco_core_enabled', false);
    }

    public function assertCanActivate(): void
    {
        $activation = config('community_finance.activation', []);

        if (($activation['mode'] ?? 'dormant') !== 'live') {
            throw new RuntimeException('Community finance remains dormant until OPFIN_COMMUNITY_FINANCE_MODE=live is deliberately approved.');
        }

        if (($activation['live_enabled'] ?? false) !== true) {
            throw new RuntimeException('Community finance live activation requires OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED=true.');
        }

        if (($activation['sacco_core_enabled'] ?? false) !== true) {
            throw new RuntimeException('The Member Cooperative Core remains locked until OPFIN_SACCO_CORE_ENABLED=true is deliberately approved.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function readinessSummary(): array
    {
        $modules = config('community_finance.modules', []);

        return [
            'overall_status' => $this->isLiveEnabled() ? 'LIVE_ENABLED' : 'DORMANT_READY',
            'live_actions_blocked' => ! $this->isLiveEnabled(),
            'required_signoffs' => config('community_finance.activation.required_signoffs', []),
            'modules' => collect($modules)->map(fn (array $module) => [
                'status' => $module['status'] ?? 'DORMANT_READY',
                'activation_gate' => $module['activation_gate'] ?? 'activation_signoff_required',
                'allowed_before_activation' => $module['allowed_before_activation'] ?? [],
                'blocked_before_activation' => $module['blocked_before_activation'] ?? [],
            ])->toArray(),
        ];
    }
}
