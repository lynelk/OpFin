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
        if ($provider !== 'cpay') {
            throw new RuntimeException(
                'Production money movement must use CPay. Set MOBILE_MONEY_PROVIDER=cpay; direct MTN/Airtel/mock routing is not allowed in OpFin.'
            );
        }

        if (($config['enable_demo_routes'] ?? false) === true) {
            throw new RuntimeException('OPFIN_ENABLE_DEMO_ROUTES=true is not allowed in production.');
        }
    }
}
