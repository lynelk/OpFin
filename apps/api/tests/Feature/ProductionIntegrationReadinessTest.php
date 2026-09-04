<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionIntegrationReadinessTest extends TestCase
{
    public function test_required_integrations_report_ready_without_exposing_secrets(): void
    {
        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', 'private-key-material');
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/webhooks/cpay');
        Config::set('services.cpay.callback_secret', 'callback-secret-material');
        Config::set('services.sms_gateway', 'YO');
        Config::set('services.yo.base_url', 'https://sms.example.test');
        Config::set('services.yo.account', 'account-id');
        Config::set('services.yo.password', 'sms-secret');

        $response = $this->getJson('/api/health/integrations')
            ->assertOk()
            ->assertJsonPath('data.production_ready', true)
            ->assertJsonPath('data.integrations.cpay.status', 'ready')
            ->assertJsonPath('data.integrations.sms.status', 'ready')
            ->assertJsonPath('data.integrations.whatsapp.status', 'inactive');

        $body = $response->getContent();
        $this->assertStringNotContainsString('private-key-material', $body);
        $this->assertStringNotContainsString('callback-secret-material', $body);
        $this->assertStringNotContainsString('sms-secret', $body);
    }

    public function test_missing_required_messaging_configuration_blocks_production_readiness(): void
    {
        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', 'private-key-material');
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/webhooks/cpay');
        Config::set('services.cpay.callback_secret', 'callback-secret-material');
        Config::set('services.sms_gateway', null);

        $this->getJson('/api/health/integrations')
            ->assertOk()
            ->assertJsonPath('data.production_ready', false)
            ->assertJsonPath('data.integrations.sms.status', 'blocked')
            ->assertJsonPath('data.integrations.sms.missing.0', 'SMS_GATEWAY');
    }
}
