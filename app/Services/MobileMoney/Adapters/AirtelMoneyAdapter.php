<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class AirtelMoneyAdapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit($transaction, '/standard/v1/disbursements/');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit($transaction, '/merchant/v1/payments/');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        $this->assertConfigured();

        if (! $transaction->provider_reference) {
            return MobileMoneyProviderResponse::failed('airtel', 'Airtel provider reference is required for status lookup.', false);
        }

        return $this->normalizeHttpResponse(
            Http::withHeaders($this->headers())->get($this->url('/standard/v1/payments/' . $transaction->provider_reference)),
            $transaction->provider_reference
        );
    }

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse
    {
        $transaction = $payload['transaction'] ?? [];
        $statusCode = $transaction['status_code'] ?? null;

        return new MobileMoneyProviderResponse(
            provider: 'airtel',
            successful: $statusCode === 'TS',
            status: $this->normalizeStatus($statusCode),
            providerReference: $transaction['id'] ?? null,
            message: 'Airtel webhook normalized without live API calls.',
            retryable: false,
            reconciliationStatus: $statusCode === 'TS'
                ? MobileMoneyTransaction::RECONCILIATION_PENDING
                : MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
            webhookEventId: $payload['event_id'] ?? $transaction['airtel_money_id'] ?? null,
            raw: $payload,
        );
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return $this->submit($transaction, '/standard/v1/reversals/', ['reason' => $reason]);
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('airtel', $reason, false, ['reason' => $reason]);
    }

    private function submit(MobileMoneyTransaction $transaction, string $path, array $extra = []): MobileMoneyProviderResponse
    {
        $this->assertConfigured();

        $providerReference = $transaction->provider_reference ?: $transaction->internal_reference;
        $payload = array_merge([
            'reference' => $providerReference,
            'subscriber' => [
                'country' => config('services.mobile_money.providers.airtel.country', 'UG'),
                'currency' => $transaction->currency,
                'msisdn' => $transaction->phone,
            ],
            'transaction' => [
                'amount' => $transaction->amount_minor,
                'country' => config('services.mobile_money.providers.airtel.country', 'UG'),
                'currency' => $transaction->currency,
                'id' => $providerReference,
            ],
        ], $extra);

        return $this->normalizeHttpResponse(Http::withHeaders($this->headers())->post($this->url($path), $payload), $providerReference);
    }

    private function normalizeHttpResponse(Response $response, ?string $providerReference = null): MobileMoneyProviderResponse
    {
        $payload = $response->json() ?: [];
        $transaction = $payload['data']['transaction'] ?? $payload['transaction'] ?? [];
        $statusCode = $transaction['status_code'] ?? $payload['status_code'] ?? null;

        if ($response->successful()) {
            return new MobileMoneyProviderResponse(
                provider: 'airtel',
                successful: $statusCode === null || $this->normalizeStatus($statusCode) !== MobileMoneyTransaction::STATUS_FAILED,
                status: $this->normalizeStatus($statusCode ?: 'TIP'),
                providerReference: $transaction['id'] ?? $payload['reference'] ?? $providerReference,
                message: $payload['message'] ?? 'Airtel Money request accepted.',
                retryable: true,
                reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
                raw: $payload ?: ['http_status' => $response->status()],
            );
        }

        return MobileMoneyProviderResponse::failed(
            'airtel',
            $payload['message'] ?? "Airtel Money request failed with HTTP {$response->status()}.",
            $response->serverError(),
            $payload ?: ['http_status' => $response->status()],
        );
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string) config('services.mobile_money.providers.airtel.access_token'),
            'X-Country' => (string) config('services.mobile_money.providers.airtel.country', 'UG'),
            'X-Currency' => (string) config('services.mobile_money.providers.airtel.currency', 'UGX'),
            'Content-Type' => 'application/json',
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.mobile_money.providers.airtel.base_url'), '/') . $path;
    }

    private function assertConfigured(): void
    {
        foreach (['base_url', 'client_id', 'access_token'] as $key) {
            if (! config("services.mobile_money.providers.airtel.{$key}")) {
                throw new InvalidArgumentException("Airtel Money {$key} is not configured.");
            }
        }
    }

    private function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'TS' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'TIP' => MobileMoneyTransaction::STATUS_PENDING,
            default => MobileMoneyTransaction::STATUS_FAILED,
        };
    }
}
