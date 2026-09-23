<?php

namespace App\Services\MobileMoney;

use App\Contracts\MobileMoneyProviderInterface;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use App\Services\MobileMoney\Adapters\MockMobileMoneyAdapter;
use InvalidArgumentException;

class MobileMoneyProviderManager
{
    public function provider(?string $name = null): MobileMoneyProviderInterface
    {
        $name ??= config('services.mobile_money.default_provider', 'cpay');

        return match ($name) {
            'cpay' => app(CpayV2Adapter::class),
            'mock' => $this->mockProvider(),
            default => $this->configuredDirectProvider($name),
        };
    }

    private function mockProvider(): MobileMoneyProviderInterface
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new InvalidArgumentException('The mock money-movement provider is allowed only in local/testing environments.');
        }

        return app(MockMobileMoneyAdapter::class);
    }

    private function configuredDirectProvider(string $name): MobileMoneyProviderInterface
    {
        $class = trim((string) config("services.mobile_money.providers.{$name}.adapter"));
        if ($class === '') {
            throw new InvalidArgumentException(
                "Direct mobile-money provider adapter '{$name}' is not configured. CPay remains the preferred route until a real adapter is installed and certified."
            );
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Configured mobile-money adapter class does not exist: {$class}");
        }

        $adapter = app($class);
        if (! $adapter instanceof MobileMoneyProviderInterface) {
            throw new InvalidArgumentException("Configured mobile-money adapter must implement ".MobileMoneyProviderInterface::class.'.');
        }

        if (app()->environment('production')
            && ! (bool) config("services.mobile_money.providers.{$name}.production_certified", false)) {
            throw new InvalidArgumentException(
                "Direct mobile-money provider '{$name}' is not certified for production."
            );
        }

        return $adapter;
    }
}
