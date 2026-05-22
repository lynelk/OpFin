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

        if (($config['mobile_money_provider'] ?? null) === 'mock') {
            throw new RuntimeException('MOBILE_MONEY_PROVIDER=mock is not allowed in production.');
        }

        if (($config['enable_demo_routes'] ?? false) === true) {
            throw new RuntimeException('OPFIN_ENABLE_DEMO_ROUTES=true is not allowed in production.');
        }
    }
}
