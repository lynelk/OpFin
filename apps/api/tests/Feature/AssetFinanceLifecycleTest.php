<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetFinanceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_finance_configuration_keeps_remote_device_controls_disabled(): void
    {
        $this->assertFalse((bool) config('asset_finance.remote_device_controls_enabled'));
        $this->assertSame('UG', config('asset_finance.jurisdiction'));
        $this->assertSame('UGX', config('asset_finance.currency'));
        $this->assertContains('fraud_screen', config('asset_finance.verticals.device.pre_approval_evidence'));
        $this->assertContains('valuation', config('asset_finance.verticals.auto.pre_approval_evidence'));
        $this->assertContains('inspection', config('asset_finance.verticals.productive.pre_approval_evidence'));
    }
}
