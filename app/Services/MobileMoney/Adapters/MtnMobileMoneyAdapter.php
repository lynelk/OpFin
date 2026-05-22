<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MtnMobileMoneyAdapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit('disbursement', $transaction, '/disbursement/v1_0/transfer');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit('collection', $transaction, '/collection/v1_0/requesttopay');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        $this->assertConfigured();

        if (! $transaction->provider_reference) {
            return MobileMoneyProviderResponse::failed('mtn', 'MTN provider reference is required for status lookup.', false);
        }

        $path = $transaction->direction === MobileMoneyTransaction::DIRECTION_DISBURSEMENT
            ? "/disbursement/v1_0/transfer/{$transaction->provider_reference}"
            : "/collection/v1_0/requesttopay/{$transaction->provider_reference}";

        return $this->normalizeHttpResponse(Http::withHeaders($this->headers())->get($this->url($path)), $transaction->provider_reference);
    }

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse
    {
        return new MobileMoneyProviderResponse(
            provider: 'mtn',
            successful: ($payload['status'] ?? null) === 'SUCCESSFUL',
            status: $this->normalizeStatus($payload['status'] ?? null),
            providerReference: $payload['externalId'] ?? null,
            message: 'MTN webhook normalized without live API calls.',
            retryable: false,
            reconciliationStatus: ($payload['status'] ?? null) === 'SUCCESSFUL'
                ? MobileMoneyTransaction::RECONCILIATION_PENDING
                : MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
            webhookEventId: $payload['event_id'] ?? $payload['financialTransactionId'] ?? null,
            raw: $payload,
        );
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return $this->submit('reversal', $transaction, '/reversal/v1_0/transfer', ['reason' => $reason]);
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('mtn', $reason, false, ['reason' => $reason]);
    }

    private function submit(string $operation, MobileMoneyTransaction $transaction, string $path, array $extra = []): MobileMoneyProviderResponse
    {
        $this->assertConfigured();

        $providerReference = $transaction->provider_reference ?: $transaction->internal_reference;
        $payload = array_merge([
            'amount' => (string) $transaction->amount_minor,
            'currency' => $transaction->currency,
            'externalId' => $transaction->internal_reference,
            'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $transaction->phone],
            'payee' => ['partyIdType' => 'MSISDN', 'partyId' => $transaction->phone],
            'payerMessage' => "OpFin {$operation}",
            'payeeNote' => "OpFin {$operation}",
        ], $extra);

        $response = Http::withHeaders(array_merge($this->headers(), [
            'X-Reference-Id' => $providerReference,
        ]))->post($this->url($path), $payload);

        return $this->normalizeHttpResponse($response, $providerReference);
    }

    private function normalizeHttpResponse(Response $response, ?string $providerReference = null): MobileMoneyProviderResponse
    {
        $payload = $response->json() ?: [];
        $status = $payload['status'] ?? $payload['financialTransactionStatus'] ?? null;

        if ($response->successful()) {
            return new MobileMoneyProviderResponse(
                provider: 'mtn',
                successful: true,
                status: $this->normalizeStatus($status ?: 'PENDING'),
                providerReference: $payload['externalId'] ?? $payload['referenceId'] ?? $providerReference,
                message: $payload['reason'] ?? 'MTN Mobile Money request accepted.',
                retryable: true,
                reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
                raw: $payload ?: ['http_status' => $response->status()],
            );
        }

        return MobileMoneyProviderResponse::failed(
            'mtn',
            $payload['message'] ?? $payload['reason'] ?? "MTN Mobile Money request failed with HTTP {$response->status()}.",
            $response->serverError(),
            $payload ?: ['http_status' => $response->status()],
        );
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . (string) config('services.mobile_money.providers.mtn.access_token'),
            'Ocp-Apim-Subscription-Key' => (string) config('services.mobile_money.providers.mtn.disbursement_sub_key'),
            'X-Target-Environment' => (string) config('services.mobile_money.providers.mtn.target_env', 'sandbox'),
            'Content-Type' => 'application/json',
        ];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.mobile_money.providers.mtn.base_url'), '/') . $path;
    }

    private function assertConfigured(): void
    {
        foreach (['base_url', 'access_token', 'disbursement_sub_key'] as $key) {
            if (! config("services.mobile_money.providers.mtn.{$key}")) {
                throw new InvalidArgumentException("MTN Mobile Money {$key} is not configured.");
            }
        }
    }

    private function normalizeStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'SUCCESSFUL' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'PENDING' => MobileMoneyTransaction::STATUS_PENDING,
            default => MobileMoneyTransaction::STATUS_FAILED,
        };
    }
}
