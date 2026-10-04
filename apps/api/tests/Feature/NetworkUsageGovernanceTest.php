<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordNetworkUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        $response->assertHeader(
            'X-OpFin-Correlation-Id',
            'app-test-correlation-001'
        );
        $response->assertHeader('X-OpFin-Sponsorship-Class', 'unknown');
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            (string) $response->headers->get(
                'X-OpFin-Application-Bytes-Out'
            )
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
        $correlation = (string) $response->headers->get(
            'X-OpFin-Correlation-Id'
        );
        $this->assertStringStartsWith('api-', $correlation);
        $this->assertNotSame('unsafe correlation with spaces', $correlation);
    }

    public function test_usage_logging_failure_cannot_change_api_outcome(): void
    {
        config()->set('opfin.data.network_usage_logging', true);
        Log::shouldReceive('info')
            ->once()
            ->andThrow(new RuntimeException('log sink unavailable'));

        $middleware = app(RecordNetworkUsage::class);
        $request = Request::create(
            '/api/test-network-usage',
            'POST',
            [],
            [],
            [],
            ['CONTENT_LENGTH' => '3']
        );

        $response = $middleware->handle(
            $request,
            static fn (): Response => response('ok', 200)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_api_prefix_is_not_recorded_as_the_feature_dimension(): void
    {
        config()->set('opfin.data.network_usage_logging', true);

        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'opfin.network_usage'
                    && ($context['feature'] ?? null) === 'health'
                    && ($context['route'] ?? null) === 'api/health';
            });

        $this->getJson('/api/health')->assertOk();
    }

    public function test_exception_rendered_responses_keep_usage_headers_and_are_metered(): void
    {
        config()->set('opfin.data.network_usage_logging', true);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'opfin.network_usage'
                    && ($context['status'] ?? null) === 500
                    && ($context['bytes_out'] ?? 0) > 0;
            });

        $middleware = app(RecordNetworkUsage::class);
        $request = Request::create('/api/test-rendered-failure', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = $middleware->handle(
            $request,
            static function (): Response {
                throw new RuntimeException('rendered failure');
            }
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertNotSame(
            '',
            (string) $response->headers->get('X-OpFin-Correlation-Id')
        );
        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            (string) $response->headers->get('X-OpFin-Application-Bytes-Out')
        );
    }

    public function test_streamed_response_bytes_are_counted_after_emission(): void
    {
        config()->set('opfin.data.network_usage_logging', true);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'opfin.network_usage'
                    && ($context['bytes_out'] ?? null) === 10
                    && ($context['streamed'] ?? null) === true
                    && ($context['stream_completed'] ?? null) === true;
            });

        $middleware = app(RecordNetworkUsage::class);
        $request = Request::create('/api/test-stream', 'GET');

        $response = $middleware->handle(
            $request,
            static fn (): StreamedResponse => new StreamedResponse(
                static function (): void {
                    echo 'hello';
                    echo 'world';
                },
                200
            )
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertSame('helloworld', $content);
        $this->assertNull(
            $response->headers->get('X-OpFin-Application-Bytes-Out')
        );
    }
}
