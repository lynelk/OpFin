<?php

namespace Tests\Unit;

use App\Support\ProductionConfiguration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProductionConfigurationTest extends TestCase
{
    public function test_allows_mock_provider_outside_production(): void
    {
        ProductionConfiguration::assertSafe(false, [
            'app_debug' => true,
            'mobile_money_provider' => 'mock',
            'enable_demo_routes' => true,
        ]);

        $this->assertTrue(true);
    }

    public function test_blocks_debug_mode_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false in production.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => true,
            'mobile_money_provider' => 'mtn',
            'enable_demo_routes' => false,
        ]);
    }

    public function test_blocks_mock_mobile_money_provider_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MOBILE_MONEY_PROVIDER=mock is not allowed in production.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'mock',
            'enable_demo_routes' => false,
        ]);
    }

    public function test_blocks_demo_routes_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPFIN_ENABLE_DEMO_ROUTES=true is not allowed in production.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'mtn',
            'enable_demo_routes' => true,
        ]);
    }
}
