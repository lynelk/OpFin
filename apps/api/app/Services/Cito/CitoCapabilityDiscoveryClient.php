<?php

namespace App\Services\Cito;

use RuntimeException;

/**
 * Server-authoritative, signed discovery. Returns what the merchant is actually
 * entitled to inspect. Never infer production certification from the response.
 */
class CitoCapabilityDiscoveryClient
{
    public function capabilities(): array
    {
        return $this->read('/api/v2/capabilities');
    }

    public function identityCapabilities(): array
    {
        return $this->read('/api/v2/identity/capabilities');
    }

    public function paymentChannels(): array
    {
        return $this->read('/api/v2/channels');
    }

    public function webhookEvents(): array
    {
        return $this->read('/api/v2/webhooks/events');
    }

    private function read(string $path): array
    {
        $response = app(SignedCitoTransport::class)->request('GET', $path, [], [
            'merchantNumber' => trim((string) config('services.cito.merchant_number')),
        ]);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('Cito capability discovery is unavailable (HTTP '.$response->status().').');
        }
        return $response->json();
    }
}
