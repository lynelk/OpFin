<?php

namespace App\Services\Cito;

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

    public function customers(): array
    {
        return $this->get('/customers');
    }

    public function usageSummary(): array
    {
        return $this->get('/usage/summary');
    }

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

    public function quote(array $request): array
    {
        foreach (['billingAccountReference', 'serviceCode', 'meterCode', 'ratingBaseAmount', 'sourceCurrency'] as $field) {
            if (! isset($request[$field]) || ! is_string($request[$field]) || trim($request[$field]) === '') {
                throw new InvalidArgumentException('Missing or invalid Cito price quote field: '.$field);
            }
        }
        if (! preg_match('/^-?\\d+(?:\\.\\d+)?$/', $request['ratingBaseAmount'])) {
            throw new InvalidArgumentException('Quote amounts must be exact decimal strings.');
        }

        return $this->call('POST', '/pricing/quotes', $request);
    }

    /**
     * Financial writes are separately enabled and require a scoped BaaS account.
     * Unknown outcomes must be resolved using the original reference, never an automatic retry.
     */
    public function authorizeCharge(array $request): array
    {
        $this->requireWrite();
        $this->requiredStrings($request, [
            'billingAccountReference', 'serviceCode', 'usageQuantity',
            'netAmount', 'currency', 'idempotencyKey',
        ]);
        $this->decimal($request, 'usageQuantity');
        $this->decimal($request, 'netAmount');

        return $this->call('POST', '/charges', $request);
    }

    public function chargeCommit(string $reference): array
    {
        $this->requireWrite();

        return $this->call('POST', '/charges/'.$this->reference($reference).'/commit');
    }

    public function chargeRelease(string $reference): array
    {
        $this->requireWrite();

        return $this->call('POST', '/charges/'.$this->reference($reference).'/release');
    }

    public function usageEvent(array $request): array
    {
        $this->requireWrite();
        $this->requiredStrings($request, ['serviceCode', 'meterCode', 'quantity', 'sourceReference', 'idempotencyKey']);
        $this->decimal($request, 'quantity');

        return $this->call('POST', '/usage/events', $request);
    }

    public function createSubscription(array $request): array
    {
        $this->requireWrite();
        $this->requiredStrings($request, [
            'customerReference', 'accountReference', 'contractReference',
            'subscriptionReference', 'serviceCode', 'planCode',
        ]);

        return $this->call('POST', '/subscriptions', $request);
    }

    public function activateSubscription(string $reference): array
    {
        $this->requireWrite();

        return $this->call('POST', '/subscriptions/'.$this->reference($reference).'/activate');
    }

    private function requiredStrings(array $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (! isset($request[$field]) || ! is_string($request[$field]) || trim($request[$field]) === '') {
                throw new InvalidArgumentException('Missing or invalid Cito BaaS field: '.$field);
            }
        }
    }

    private function decimal(array $request, string $field): void
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', (string) $request[$field])) {
            throw new InvalidArgumentException('Cito BaaS '.$field.' must be an exact decimal string.');
        }
    }

    private function requireWrite(): void
    {
        if (! (bool) config('services.cito.baas_write_enabled', false)) {
            throw new RuntimeException('Cito BaaS writes require separately approved activation.');
        }
    }

    public function chargeStatus(string $reference): array
    {
        return $this->get('/charges/'.rawurlencode($this->reference($reference)));
    }

    public function invoice(string $number): array
    {
        return $this->get('/invoices/'.rawurlencode($this->reference($number)));
    }

    private function reference(string $reference): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $reference)) {
            throw new InvalidArgumentException('Invalid Cito billing reference.');
        }

        return $reference;
    }

    private function get(string $path): array
    {
        return $this->call('GET', $path);
    }

    private function call(string $method, string $path, array $body = []): array
    {
        app(CitoFeatureGate::class)->requireEnabled('billing');
        $base = trim((string) config('services.cito.base_url'));
        $key = trim((string) config('services.cito.baas_api_key'));
        $environment = strtoupper(trim((string) config('services.cito.environment', 'SANDBOX')));

        if ($base === '' || $key === '' || ! in_array($environment, ['SANDBOX', 'PRODUCTION'], true)) {
            throw new InvalidArgumentException('Cito BaaS is not provisioned for this environment.');
        }
        $parts = parse_url($base);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Cito BaaS requires an approved HTTPS origin.');
        }

        $request = Http::withOptions(['allow_redirects' => false])->withHeaders([
            'Accept' => 'application/json',
            'X-Cito-Api-Key' => $key,
            'X-Cito-Environment' => $environment,
        ])->timeout((int) config('services.cito.timeout_seconds', 15));
        $url = rtrim($base, '/').self::PREFIX.$path;
        $response = $method === 'GET' ? $request->get($url) : $request->post($url, $body);

        if (! $response->successful()) {
            throw new RuntimeException('Cito BaaS request failed (HTTP '.$response->status().').');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Cito BaaS response is not valid JSON.');
        }

        return $json;
    }
}
