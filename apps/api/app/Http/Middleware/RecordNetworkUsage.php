<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RecordNetworkUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $correlationId = $this->correlationId($request);
        $request->headers->set('X-OpFin-Correlation-Id', $correlationId);

        $response = $next($request);

        $bytesIn = max(0, (int) ($request->server('CONTENT_LENGTH') ?? 0));
        $bytesOut = $this->responseBytes($response);
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
        $response->headers->set('X-OpFin-Application-Bytes-In', (string) $bytesIn);
        $response->headers->set('X-OpFin-Application-Bytes-Out', (string) $bytesOut);
        $response->headers->set('X-OpFin-Sponsorship-Class', $sponsorship);

        if ((bool) config('opfin.data.network_usage_logging', true)) {
            Log::info('opfin.network_usage', [
                'correlation_id' => $correlationId,
                'feature' => $feature,
                'operation' => $operation,
                'client_feature' => $clientFeature,
                'client_operation' => $clientOperation,
                'route' => $routeTemplate,
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'bytes_in' => $bytesIn,
                'bytes_out' => $bytesOut,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'sponsorship_class' => $sponsorship,
                'operator_reference' => (string) config('opfin.data.sponsorship_operator_reference', ''),
                'distribution_channel' => $this->dimension(
                    (string) $request->header('X-OpFin-Distribution-Channel', 'unknown')
                ),
            ]);
        }

        return $response;
    }

    private function correlationId(Request $request): string
    {
        $candidate = trim((string) $request->header('X-OpFin-Correlation-Id', ''));
        if ($candidate !== ''
            && strlen($candidate) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $candidate) === 1) {
            return $candidate;
        }

        return 'api-'.Str::uuid()->toString();
    }

    private function responseBytes(Response $response): int
    {
        $declared = $response->headers->get('Content-Length');
        if (is_string($declared) && ctype_digit($declared)) {
            return (int) $declared;
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if (is_string($content)) {
                return strlen($content);
            }
        }

        return 0;
    }

    private function dimension(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.:\/{}-]+/', '_', $value) ?? 'unknown';
        $value = trim($value, '_');

        return substr($value !== '' ? $value : 'unknown', 0, 96);
    }
}
