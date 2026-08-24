<?php

namespace App\Services\MobileMoney;

use App\Contracts\MobileMoneyProviderInterface;
use App\Services\MobileMoney\Adapters\AirtelMoneyAdapter;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use App\Services\MobileMoney\Adapters\MockMobileMoneyAdapter;
use App\Services\MobileMoney\Adapters\MtnMobileMoneyAdapter;
use InvalidArgumentException;

class MobileMoneyProviderManager
{
    public function provider(?string $name = null): MobileMoneyProviderInterface
    {
        $name ??= config('services.mobile_money.default_provider', 'mock');

        return match ($name) {
            'mock' => app(MockMobileMoneyAdapter::class),
            'cpay' => app(CpayV2Adapter::class),
            'mtn' => app(MtnMobileMoneyAdapter::class),
            'airtel' => app(AirtelMoneyAdapter::class),
            default => throw new InvalidArgumentException("Unsupported mobile money provider: {$name}"),
        };
    }
}
