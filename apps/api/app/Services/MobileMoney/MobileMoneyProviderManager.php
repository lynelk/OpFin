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
        $name = strtolower(trim((string) ($name ?? config('services.mobile_money.default_provider', 'cpay'))));

        return match ($name) {
            'cpay' => app(CpayV2Adapter::class),
            'mock' => $this->mockProvider(),
            default => $this->configuredDirectProvider($name),
        };
    }

    public function assertReadyForSubmission(?string $name = null): void
    {
        $name = strtolower(trim((string) ($name ?? config('services.mobile_money.default_provider', 'cpay'))));

        if ($name === 'cpay') {
            $missing = collect([
                'base_url' => config('services.cpay.base_url'),
                'merchant_number' => config('services.cpay.merchant_number'),
                'private_key' => config('services.cpay.private_key'),
                'callback_url' => config('services.cpay.callback_url'),
            ])->filter(fn ($value) => ! is_string($value) || trim($value) === '')
                ->keys()
                ->values()
                ->all();

            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'CPay submission is not configured: '.implode(', ', $missing).'.'
                );
            }

            return;
        }

        if ($name === 'mock') {
            $this->mockProvider();

            return;
        }

        $this->configuredDirectProvider($name);
    }

    public function readiness(?string $name = null): array
    {
        $name = strtolower(trim((string) ($name ?? config('services.mobile_money.default_provider', 'cpay'))));

        if ($name === 'cpay') {
            $fields = [
                'base_url' => config('services.cpay.base_url'),
                'merchant_number' => config('services.cpay.merchant_number'),
                'private_key' => config('services.cpay.private_key'),
                'callback_url' => config('services.cpay.callback_url'),
                'callback_secret' => config('services.cpay.callback_secret'),
            ];
            $missing = collect($fields)
                ->filter(fn ($value) => ! is_string($value) || trim($value) === '')
                ->keys()
                ->values()
                ->all();
            $certified = (bool) config('services.mobile_money.providers.cpay.production_certified', false);

            return [
                'provider' => 'cpay',
                'status' => $missing === [] && $certified ? 'ready' : 'blocked',
                'certified' => $certified,
                'missing' => $missing,
                'configured_fields' => collect($fields)->keys()->diff($missing)->values()->all(),
            ];
        }

        if ($name === 'mock') {
            return [
                'provider' => 'mock',
                'status' => 'blocked',
                'certified' => false,
                'missing' => [],
                'reason' => 'The mock provider is not production-equivalent financial readiness evidence.',
            ];
        }

        $class = trim((string) config("services.mobile_money.providers.{$name}.adapter"));
        $certified = (bool) config("services.mobile_money.providers.{$name}.production_certified", false);
        $classValid = $class !== ''
            && class_exists($class)
            && is_a($class, MobileMoneyProviderInterface::class, true);

        return [
            'provider' => $name,
            'status' => $classValid && $certified ? 'ready' : 'blocked',
            'certified' => $certified,
            'adapter_class_configured' => $class !== '',
            'adapter_class_valid' => $classValid,
            'missing' => $class === '' ? ['adapter'] : [],
        ];
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
