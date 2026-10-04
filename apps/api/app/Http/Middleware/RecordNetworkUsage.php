<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RecordNetworkUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $correlationId = $this->correlationId($request);
        $request->headers->set('X-OpFin-Correlation-Id', $correlationId);

        $response = $next($request);

        $bytesIn = max(0, (int) ($request->server('CONTENT_LENGTH') ?? 0));
        $route = $request->route();
        $routeTemplate = $route?->uri() ?? ltrim($request->path(), '/');
        $routeSegments = array_values(array_filter(
            explode('/', trim($routeTemplate, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
        $feature = $this->dimension((string) ($routeSegments[0] ?? 'core'));
        $operation = $this->dimension($request->method().' '.$routeTemplate);
        $clientFeature = $this->dimension(
            (string) $request->header('X-OpFin-Feature', 'unknown')
        );
        $clientOperation = $this->dimension(
            (string) $request->header('X-OpFin-Operation', 'unknown')
        );

        $sponsorship = (bool) config('opfin.data.sponsorship_confirmed', false)
            ? 'sponsored'
            : 'unknown';

        $response->headers->set('X-OpFin-Correlation-Id', $correlationId);
        $response->headers->set(
            'X-OpFin-Application-Bytes-In',
            (string) $bytesIn
        );
        $response->headers->set('X-OpFin-Sponsorship-Class', $sponsorship);

        $context = [
            'correlation_id' => $correlationId,
            'feature' => $feature,
            'operation' => $operation,
            'client_feature' => $clientFeature,
            'client_operation' => $clientOperation,
            'route' => $routeTemplate,
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'bytes_in' => $bytesIn,
            'sponsorship_class' => $sponsorship,
            'operator_reference' => (string) config(
                'opfin.data.sponsorship_operator_reference',
                ''
            ),
            'distribution_channel' => $this->dimension(
                (string) $request->header(
                    'X-OpFin-Distribution-Channel',
                    'unknown'
                )
            ),
        ];

        $declaredBytes = $this->declaredResponseBytes($response);
        if ($response instanceof StreamedResponse && $declaredBytes === null) {
            if ((bool) config('opfin.data.network_usage_logging', true)) {
                $this->observeStreamedResponse(
                    $response,
                    $context,
                    $started
                );
            }

            return $response;
        }

        $bytesOut = $declaredBytes ?? $this->responseBodyBytes($response);
        $response->headers->set(
            'X-OpFin-Application-Bytes-Out',
            (string) $bytesOut
        );

        if ((bool) config('opfin.data.network_usage_logging', true)) {
            $this->safeLog([
                ...$context,
                'bytes_out' => $bytesOut,
                'duration_ms' => $this->durationMs($started),
                'streamed' => false,
            ]);
        }

        return $response;
    }

    private function observeStreamedResponse(
        StreamedResponse $response,
        array $context,
        float $started
    ): void {
        $callback = $response->getCallback();

        if ($callback === null) {
            $this->safeLog([
                ...$context,
                'bytes_out' => 0,
                'duration_ms' => $this->durationMs($started),
                'streamed' => true,
                'stream_completed' => false,
            ]);

            return;
        }

        $response->setCallback(function () use (
            $callback,
            $context,
            $started
        ): void {
            $bytesOut = 0;
            $bufferLevel = ob_get_level();
            $completed = false;

            ob_start(
                static function (string $chunk) use (&$bytesOut): string {
                    $bytesOut += strlen($chunk);

                    return $chunk;
                },
                1
            );

            try {
                $callback();
                $completed = true;
            } finally {
                while (ob_get_level() > $bufferLevel) {
                    ob_end_flush();
                }

                $this->safeLog([
                    ...$context,
                    'bytes_out' => $bytesOut,
                    'duration_ms' => $this->durationMs($started),
                    'streamed' => true,
                    'stream_completed' => $completed,
                ]);
            }
        });
    }

    private function safeLog(array $context): void
    {
        try {
            Log::info('opfin.network_usage', $context);
        } catch (Throwable) {
            // Usage telemetry is non-authoritative. A logging outage must never
            // turn a committed financial action into an apparent API failure.
        }
    }

    private function correlationId(Request $request): string
    {
        $candidate = trim(
            (string) $request->header('X-OpFin-Correlation-Id', '')
        );
        if (
            $candidate !== ''
            && strlen($candidate) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $candidate) === 1
        ) {
            return $candidate;
        }

        return 'api-'.Str::uuid()->toString();
    }

    private function declaredResponseBytes(Response $response): ?int
    {
        $declared = $response->headers->get('Content-Length');
        if (is_string($declared) && ctype_digit($declared)) {
            return (int) $declared;
        }

        return null;
    }

    private function responseBodyBytes(Response $response): int
    {
        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content)) {
                return strlen($content);
            }
        }

        return 0;
    }

    private function durationMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function dimension(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace(
            '/[^a-z0-9_.:\/{}-]+/',
            '_',
            $value
        ) ?? 'unknown';
        $value = trim($value, '_');

        return substr($value !== '' ? $value : 'unknown', 0, 96);
    }
}
