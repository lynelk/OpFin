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
            'community_finance_mode' => 'live',
            'community_finance_live_enabled' => true,
            'sacco_core_enabled' => true,
            'community_finance_public_routes_enabled' => true,
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

    public function test_blocks_non_cpay_money_movement_provider_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Production money movement must use CPay. Set MOBILE_MONEY_PROVIDER=cpay; direct MTN/Airtel/mock routing is not allowed in OpFin.');

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
            'mobile_money_provider' => 'cpay',
            'enable_demo_routes' => true,
        ]);
    }

    public function test_blocks_community_finance_live_mode_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPFIN_COMMUNITY_FINANCE_MODE=live is blocked in production until the Community Finance activation runbook is approved and executed.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'cpay',
            'enable_demo_routes' => false,
            'community_finance_mode' => 'live',
        ]);
    }

    public function test_blocks_community_finance_live_flag_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPFIN_COMMUNITY_FINANCE_LIVE_ENABLED=true is blocked in production until the Community Finance activation runbook is approved and executed.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'cpay',
            'enable_demo_routes' => false,
            'community_finance_live_enabled' => true,
        ]);
    }

    public function test_blocks_sacco_core_activation_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPFIN_SACCO_CORE_ENABLED=true is blocked in production until the Member Cooperative Core activation runbook is approved and executed.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'cpay',
            'enable_demo_routes' => false,
            'sacco_core_enabled' => true,
        ]);
    }

    public function test_blocks_public_community_finance_routes_in_production(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OPFIN_COMMUNITY_FINANCE_PUBLIC_ROUTES_ENABLED=true is blocked in production until public member terms, disclosures and support readiness are approved.');

        ProductionConfiguration::assertSafe(true, [
            'app_debug' => false,
            'mobile_money_provider' => 'cpay',
            'enable_demo_routes' => false,
            'community_finance_public_routes_enabled' => true,
        ]);
    }
}
