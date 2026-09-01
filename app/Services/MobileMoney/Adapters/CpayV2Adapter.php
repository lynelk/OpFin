<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CpayV2Adapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit(transaction: $transaction, path: '/api/v2/native/payments/payout', partyKey: 'payee');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->submit(transaction: $transaction, path: '/api/v2/native/payments/collect', partyKey: 'payer');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        $this->assertConfigured();
        $reference = rawurlencode($transaction->internal_reference);
        $path = "/api/v2/payments/{$reference}";
        $query = ['merchantNumber' => $this->merchantNumber()];
        $response = $this->sendSigned('GET', $path, $query, null, null);

        return $this->normalizeHttpResponse($response, $transaction->provider_reference ?: $transaction->internal_reference);
    }

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $eventType = strtolower(trim((string) ($payload['eventType'] ?? $payload['event_type'] ?? '')));
        $status = strtoupper(trim((string) ($payload['status'] ?? $data['status'] ?? '')));

        if ($status === '') {
            $status = match ($eventType) {
                'payment.completed', 'payment.succeeded', 'payment.successful',
                'payout.completed', 'payout.succeeded', 'payout.successful' => 'SUCCESSFUL',
                'payment.failed', 'payout.failed', 'refund.failed', 'reversal.failed' => 'FAILED',
                'refund.completed', 'refund.succeeded', 'refund.successful',
                'payment.refunded', 'payment.reversed', 'payout.reversed',
                'reversal.completed', 'reversal.succeeded', 'reversal.successful' => 'REVERSED',
                default => 'PENDING',
            };
        }

        $normalizedStatus = $this->normalizeStatus($status);
        $providerReference = $payload['transactionId']
            ?? $payload['transaction_id']
            ?? $data['transactionId']
            ?? $data['transaction_id']
            ?? $payload['reference']
            ?? $data['reference']
            ?? null;

        return new MobileMoneyProviderResponse(
            provider: 'cpay',
            successful: $normalizedStatus !== MobileMoneyTransaction::STATUS_FAILED,
            status: $normalizedStatus,
            providerReference: $providerReference,
            message: 'CPay webhook event normalized.',
            retryable: $normalizedStatus === MobileMoneyTransaction::STATUS_PENDING,
            reconciliationStatus: $this->reconciliationStatus($normalizedStatus),
            webhookEventId: $payload['eventId'] ?? $payload['event_id'] ?? null,
            raw: $payload,
        );
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        throw new InvalidArgumentException('CPay v2 reversal is not enabled until a certified refund/reversal request contract is configured. Existing transaction state was left unchanged.');
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('cpay', $reason, false, ['reason' => $reason]);
    }

    private function submit(MobileMoneyTransaction $transaction, string $path, string $partyKey): MobileMoneyProviderResponse
    {
        $this->assertConfigured();

        $correlationId = (string) ($transaction->metadata['correlation_id'] ?? $transaction->internal_reference);
        $traceId = (string) ($transaction->metadata['trace_id'] ?? $correlationId);
        $productReference = (string) ($transaction->metadata['product_reference'] ?? $transaction->internal_reference);
        $customerReference = (string) ($transaction->metadata['customer_reference'] ?? ($transaction->user_id ? 'user:'.$transaction->user_id : 'anonymous'));
        $purpose = (string) ($transaction->metadata['purpose'] ?? $transaction->direction);
        $country = (string) config('services.cpay.country', 'UG');
        $channel = $transaction->metadata['channel'] ?? config('services.cpay.channel');

        $metadata = [
            'correlationId' => $correlationId,
            'traceId' => $traceId,
            'productReference' => $productReference,
            'customerReference' => $customerReference,
            'purpose' => $purpose,
            'country' => $country,
            'callbackEventRoute' => (string) config('services.cpay.callback_url'),
            'opfinTransactionId' => (string) $transaction->getKey(),
        ];
        if (is_string($channel) && trim($channel) !== '') {
            $metadata['preferredChannel'] = trim($channel);
        }

        $payload = [
            'merchantNumber' => $this->merchantNumber(),
            'country' => $country,
            'currency' => $transaction->currency,
            'amount' => $this->formatAmount($transaction->amount_minor),
            'reference' => $transaction->internal_reference,
            'description' => (string) ($transaction->metadata['description'] ?? "OpFin {$transaction->direction}"),
            'callbackUrl' => (string) config('services.cpay.callback_url'),
            'metadata' => $metadata,
            $partyKey => ['type' => 'MSISDN', 'value' => $transaction->phone],
        ];
        if (is_string($channel) && trim($channel) !== '') {
            $payload['channel'] = trim($channel);
        }

        $response = $this->sendSigned('POST', $path, [], $payload, $transaction->idempotency_key);

        return $this->normalizeHttpResponse($response, $transaction->internal_reference);
    }

    private function sendSigned(string $method, string $path, array $query, ?array $payload, ?string $idempotencyKey): Response
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $canonicalQuery = $this->canonicalQuery($query);
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", [strtoupper($method), $path, $canonicalQuery, $timestamp, $nonce, hash('sha256', $body)]);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CPay-Merchant-Number' => $this->merchantNumber(),
            'X-CPay-Signature-Version' => 'v2',
            'X-CPay-Timestamp' => $timestamp,
            'X-CPay-Nonce' => $nonce,
            'X-CPay-Signature' => $this->sign($canonical),
            'X-CPay-Environment' => (string) config('services.cpay.environment', 'sandbox'),
        ];
        if ($idempotencyKey) {
            $headers['X-CPay-Idempotency-Key'] = $idempotencyKey;
        }

        $request = Http::withHeaders($headers)
            ->timeout((int) config('services.cpay.timeout_seconds', 30))
            ->retry((int) config('services.cpay.connect_retries', 1), (int) config('services.cpay.retry_delay_ms', 250), throw: false);

        $url = rtrim((string) config('services.cpay.base_url'), '/').$path;
        if ($canonicalQuery !== '') {
            $url .= '?'.$canonicalQuery;
        }

        return $request->withBody($body, 'application/json')->send(strtoupper($method), $url);
    }

    private function normalizeHttpResponse(Response $response, ?string $fallbackReference): MobileMoneyProviderResponse
    {
        $payload = $response->json() ?: [];
        $status = strtoupper((string) ($payload['status'] ?? ''));
        if ($response->successful()) {
            $normalizedStatus = $this->normalizeStatus($status ?: 'PENDING');

            return new MobileMoneyProviderResponse(
                provider: 'cpay',
                successful: $normalizedStatus !== MobileMoneyTransaction::STATUS_FAILED,
                status: $normalizedStatus,
                providerReference: $payload['transactionId'] ?? $payload['reference'] ?? $fallbackReference,
                message: $payload['message'] ?? 'CPay request accepted.',
                retryable: $normalizedStatus === MobileMoneyTransaction::STATUS_PENDING,
                reconciliationStatus: $this->reconciliationStatus($normalizedStatus),
                raw: $this->safePayload($payload, $response->status()),
            );
        }

        return MobileMoneyProviderResponse::failed(
            'cpay',
            $payload['message'] ?? "CPay request failed with HTTP {$response->status()}.",
            $response->serverError() || $response->status() === 429,
            $this->safePayload($payload, $response->status()),
        );
    }

    private function reconciliationStatus(string $status): string
    {
        return match ($status) {
            MobileMoneyTransaction::STATUS_FAILED => MobileMoneyTransaction::RECONCILIATION_MATCHED,
            MobileMoneyTransaction::STATUS_SUCCESSFUL,
            MobileMoneyTransaction::STATUS_REVERSED,
            MobileMoneyTransaction::STATUS_PENDING,
            MobileMoneyTransaction::STATUS_PROCESSING => MobileMoneyTransaction::RECONCILIATION_PENDING,
            default => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
        };
    }

    private function safePayload(array $payload, int $httpStatus): array
    {
        unset($payload['providerResponse'], $payload['signature'], $payload['token'], $payload['secret']);
        $payload['http_status'] = $httpStatus;

        return $payload;
    }

    private function normalizeStatus(?string $status): string
    {
        return match (strtoupper(trim((string) $status))) {
            'SUCCESSFUL', 'SUCCEEDED', 'COMPLETED', 'SUCCESS' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'FAILED', 'REJECTED', 'CANCELLED', 'CANCELED' => MobileMoneyTransaction::STATUS_FAILED,
            'REVERSED', 'REFUNDED', 'REVERSE_COMPLETED', 'REFUND_COMPLETED' => MobileMoneyTransaction::STATUS_REVERSED,
            default => MobileMoneyTransaction::STATUS_PENDING,
        };
    }

    private function canonicalQuery(array $query): string
    {
        if ($query === []) {
            return '';
        }
        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function sign(string $canonical): string
    {
        $privateKeyValue = str_replace('\\n', "\n", (string) config('services.cpay.private_key'));
        $privateKey = openssl_pkey_get_private($privateKeyValue);
        if ($privateKey === false) {
            throw new RuntimeException('CPay private key is invalid or unreadable.');
        }
        $signed = openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new RuntimeException('Unable to sign CPay v2 request.');
        }

        return base64_encode($signature);
    }

    private function formatAmount(int $amountMinor): string
    {
        $exponent = (int) config('services.cpay.minor_unit_exponent', 0);
        if ($exponent <= 0) {
            return (string) $amountMinor;
        }
        $divisor = 10 ** $exponent;
        $major = intdiv($amountMinor, $divisor);
        $fraction = $amountMinor % $divisor;

        return sprintf('%d.%0'.$exponent.'d', $major, $fraction);
    }

    private function merchantNumber(): string
    {
        return (string) config('services.cpay.merchant_number');
    }

    private function assertConfigured(): void
    {
        foreach (['base_url', 'merchant_number', 'private_key', 'callback_url'] as $key) {
            if (! config("services.cpay.{$key}")) {
                throw new InvalidArgumentException("CPay {$key} is not configured.");
            }
        }
    }
}
