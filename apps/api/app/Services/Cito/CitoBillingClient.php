<?php

namespace App\Services\Cito;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * BaaS requires a distinct scoped service-account credential.
 * This client never falls back to the RSA merchant private key.
 */
class CitoBillingClient
{
    private const PREFIX = '/api/v2/native/billing/baas';

    public function catalogue(): array
    {
        return $this->get('/catalog');
    }

    public function entitlements(): array
    {
        return $this->get('/entitlements');
    }

    public function quotas(): array
    {
        return $this->get('/quotas');
    }

    public function invoices(): array
    {
        return $this->get('/invoices');
    }

    private function get(string $path): array
    {
        $base = trim((string) config('services.cito.base_url'));
        $key = trim((string) config('services.cito.baas_api_key'));
        $environment = strtoupper(trim((string) config('services.cito.environment', 'SANDBOX')));

        if ($base === '' || $key === '' || ! in_array($environment, ['SANDBOX', 'PRODUCTION'], true)) {
            throw new InvalidArgumentException('Cito BaaS is not provisioned for this environment.');
        }
        $parts = parse_url($base);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Cito BaaS requires an approved HTTPS origin.');
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Cito-Api-Key' => $key,
            'X-Cito-Environment' => $environment,
        ])->timeout((int) config('services.cito.timeout_seconds', 15))
            ->get(rtrim($base, '/').self::PREFIX.$path);

        if (! $response->successful()) {
            throw new RuntimeException('Cito BaaS read failed (HTTP '.$response->status().').');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Cito BaaS response is not valid JSON.');
        }

        return $json;
    }
}
