<?php

namespace Tests\Unit;

use App\Services\Cito\CitoFeatureGate;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CitoFeatureGateTest extends TestCase
{
    public function test_undocumented_external_channels_never_activate(): void
    {
        Config::set('services.cito.environment', 'SANDBOX');
        Config::set('services.cito.feature_flags.whatsapp', true);
        Config::set('services.cito.feature_flags.ussd', true);
        $gate = app(CitoFeatureGate::class);
        $this->assertFalse($gate->enabled('whatsapp'));
        $this->assertFalse($gate->enabled('ussd'));
    }

    public function test_production_requires_explicit_activation_and_acceptance(): void
    {
        Config::set('services.cito.environment', 'PRODUCTION');
        Config::set('services.cito.feature_flags.sms', true);
        Config::set('services.cito.accepted_capabilities.sms', false);
        $gate = app(CitoFeatureGate::class);
        $this->assertFalse($gate->enabled('sms'));

        Config::set('services.cito.accepted_capabilities.sms', true);
        $this->assertTrue($gate->enabled('sms'));
    }
}
