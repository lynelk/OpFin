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
            'mtn', 'airtel' => throw new InvalidArgumentException(
                'Direct mobile-money provider adapters are retired from OpFin. Route all money movement through CPay.'
            ),
            default => throw new InvalidArgumentException("Unsupported mobile money provider: {$name}"),
        };
    }

    private function mockProvider(): MobileMoneyProviderInterface
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new InvalidArgumentException('The mock money-movement provider is allowed only in local/testing environments.');
        }

        return app(MockMobileMoneyAdapter::class);
    }
}
