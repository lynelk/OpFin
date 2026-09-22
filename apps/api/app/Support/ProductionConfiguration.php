<?php

namespace App\Support;

use RuntimeException;

class ProductionConfiguration
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function assertSafe(bool $isProduction, array $config): void
    {
        if (! $isProduction) {
            return;
        }

        if (($config['app_debug'] ?? false) === true) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }

        $provider = strtolower(trim((string) ($config['mobile_money_provider'] ?? '')));
        if ($provider === 'mock' || $provider === '') {
            throw new RuntimeException('Production money movement cannot use the mock or an empty provider.');
        }

        if (($config['mobile_money_provider_certified'] ?? false) !== true) {
            throw new RuntimeException(
                "Production money-movement provider '{$provider}' is not marked as certified. CPay is the preferred route; any direct adapter requires explicit production certification."
            );
        }

        if (($config['enable_demo_routes'] ?? false) === true) {
            throw new RuntimeException('OPFIN_ENABLE_DEMO_ROUTES=true is not allowed in production.');
        }

        $communityFinanceMode = strtolower(trim((string) ($config['community_finance_mode'] ?? 'dormant')));
        if ($communityFinanceMode === 'live') {
            throw new RuntimeException(
                'OPFIN_COMMUNITY_FINANCE_MODE=live is blocked in production until the Community Finance activation runbook is approved and executed.'
            );
        }

        if (($config['community_finance_live_enabled'] ?? false) === true) {
            throw new RuntimeException(
                'OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED=true is blocked in production until the Community Finance activation runbook is approved and executed.'
            );
        }

        if (($config['sacco_core_enabled'] ?? false) === true) {
            throw new RuntimeException(
                'OPFIN_SACCO_CORE_ENABLED=true is blocked in production until the Member Cooperative Core activation runbook is approved and executed.'
            );
        }

        if (($config['community_finance_public_routes_enabled'] ?? false) === true) {
            throw new RuntimeException(
                'OPFIN_COMMUNITY_FINANCE_PUBLIC_ROUTES_ENABLED=true is blocked in production until public member terms, disclosures and support readiness are approved.'
            );
        }
    }
}
