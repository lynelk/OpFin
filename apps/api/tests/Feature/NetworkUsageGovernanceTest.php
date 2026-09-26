<?php

namespace Tests\Feature;

use Tests\TestCase;

class NetworkUsageGovernanceTest extends TestCase
{
    public function test_api_responses_expose_measurement_without_claiming_unconfirmed_sponsorship(): void
    {
        config()->set('opfin.data.sponsorship_confirmed', false);
        config()->set('opfin.data.network_usage_logging', false);

        $response = $this->withHeader(
            'X-OpFin-Correlation-Id',
            'app-test-correlation-001'
        )->getJson('/api/health');

        $response->assertOk();
        $response->assertHeader('X-OpFin-Correlation-Id', 'app-test-correlation-001');
        $response->assertHeader('X-OpFin-Sponsorship-Class', 'unknown');
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            (string) $response->headers->get('X-OpFin-Application-Bytes-Out')
        );
    }

    public function test_invalid_client_correlation_id_is_replaced(): void
    {
        config()->set('opfin.data.network_usage_logging', false);

        $response = $this->withHeader(
            'X-OpFin-Correlation-Id',
            'unsafe correlation with spaces'
        )->getJson('/api/health');

        $response->assertOk();
        $correlation = (string) $response->headers->get('X-OpFin-Correlation-Id');
        $this->assertStringStartsWith('api-', $correlation);
        $this->assertNotSame('unsafe correlation with spaces', $correlation);
    }
}
