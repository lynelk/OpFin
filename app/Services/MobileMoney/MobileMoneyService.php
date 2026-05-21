<?php

namespace App\Services\MobileMoney;

use App\Models\MobileMoneyTransaction;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MobileMoneyService
{
    public function __construct(
        private readonly MobileMoneyProviderManager $providers,
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function disburse(array $attributes, ?string $providerName = null): MobileMoneyTransaction
    {
        return $this->startTransaction(MobileMoneyTransaction::DIRECTION_DISBURSEMENT, $attributes, $providerName);
    }

    public function collect(array $attributes, ?string $providerName = null): MobileMoneyTransaction
    {
        return $this->startTransaction(MobileMoneyTransaction::DIRECTION_COLLECTION, $attributes, $providerName);
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyTransaction
    {
        $response = $this->providers->provider($transaction->provider)->lookupStatus($transaction);
        $this->applyProviderResponse($transaction, $response, [
            'last_status_checked_at' => now(),
        ]);
        $this->audit('mobile_money.status_checked', $transaction, ['response' => $response->raw]);

        return $transaction->fresh();
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyTransaction
    {
        $response = $this->providers->provider($transaction->provider)->reverse($transaction, $reason);
        $this->applyProviderResponse($transaction, $response);
        $this->audit('mobile_money.reversal.requested', $transaction, [
            'reason' => $reason,
            'response' => $response->raw,
        ]);

        return $transaction->fresh();
    }

    public function fail(MobileMoneyTransaction $transaction, string $reason): MobileMoneyTransaction
    {
        $response = $this->providers->provider($transaction->provider)->handleFailure($transaction, $reason);
        $this->applyProviderResponse($transaction, $response, [
            'failure_reason' => $reason,
            'retry_count' => $transaction->retry_count + 1,
            'next_retry_at' => $transaction->retry_count + 1 < $transaction->max_retries ? now()->addMinutes(5) : null,
        ]);
        $this->audit('mobile_money.transaction.failed', $transaction, [
            'reason' => $reason,
            'retryable' => $response->retryable,
        ]);

        return $transaction->fresh();
    }

    public function processWebhook(string $providerName, array $payload, array $headers = []): MobileMoneyTransaction
    {
        $secret = config("services.mobile_money.providers.{$providerName}.webhook_secret");
        if (!$this->signatureValidator->isValid($payload, $headers, $secret)) {
            throw new InvalidArgumentException('Invalid mobile money webhook signature.');
        }

        $provider = $this->providers->provider($providerName);
        $response = $provider->processWebhook($payload, $headers);

        if ($response->webhookEventId) {
            $duplicate = MobileMoneyTransaction::where('webhook_event_id', $response->webhookEventId)->first();
            if ($duplicate) {
                $this->audit('mobile_money.webhook.duplicate', $duplicate, [
                    'provider' => $providerName,
                    'webhook_event_id' => $response->webhookEventId,
                ]);

                return $duplicate;
            }
        }

        $transaction = MobileMoneyTransaction::where('provider', $providerName)
            ->where('provider_reference', $response->providerReference)
            ->firstOrFail();

        $this->applyProviderResponse($transaction, $response, [
            'webhook_event_id' => $response->webhookEventId,
            'webhook_received_at' => now(),
        ]);
        $this->audit('mobile_money.webhook.processed', $transaction, [
            'provider' => $providerName,
            'webhook_event_id' => $response->webhookEventId,
            'response' => $response->raw,
        ]);

        return $transaction->fresh();
    }

    private function startTransaction(string $direction, array $attributes, ?string $providerName): MobileMoneyTransaction
    {
        $providerName ??= config('services.mobile_money.default_provider', 'mock');
        $idempotencyKey = Arr::get($attributes, 'idempotency_key');

        if (!$idempotencyKey) {
            throw new InvalidArgumentException('A mobile money idempotency key is required.');
        }

        return DB::transaction(function () use ($direction, $attributes, $providerName, $idempotencyKey) {
            $existing = MobileMoneyTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $this->audit("mobile_money.{$direction}.duplicate", $existing, [
                    'idempotency_key' => $idempotencyKey,
                ]);

                return $existing;
            }

            $transaction = MobileMoneyTransaction::create([
                'transaction_id' => Arr::get($attributes, 'transaction_id'),
                'user_id' => Arr::get($attributes, 'user_id'),
                'institution_id' => Arr::get($attributes, 'institution_id'),
                'provider' => $providerName,
                'direction' => $direction,
                'amount_minor' => Arr::get($attributes, 'amount_minor'),
                'currency' => Arr::get($attributes, 'currency', 'UGX'),
                'phone' => Arr::get($attributes, 'phone'),
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => Arr::get($attributes, 'internal_reference', (string) Str::uuid()),
                'status' => MobileMoneyTransaction::STATUS_PROCESSING,
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
                'metadata' => Arr::except($attributes, [
                    'transaction_id',
                    'user_id',
                    'institution_id',
                    'provider',
                    'direction',
                    'amount_minor',
                    'currency',
                    'phone',
                    'idempotency_key',
                    'internal_reference',
                ]),
            ]);

            $this->audit("mobile_money.{$direction}.requested", $transaction, [
                'idempotency_key' => $idempotencyKey,
            ]);

            $provider = $this->providers->provider($providerName);
            $response = $direction === MobileMoneyTransaction::DIRECTION_DISBURSEMENT
                ? $provider->disburse($transaction)
                : $provider->collect($transaction);

            $this->applyProviderResponse($transaction, $response);
            $this->audit("mobile_money.{$direction}.provider_response", $transaction, [
                'response' => $response->raw,
            ]);

            return $transaction->fresh();
        });
    }

    private function applyProviderResponse(
        MobileMoneyTransaction $transaction,
        MobileMoneyProviderResponse $response,
        array $extra = [],
    ): void {
        $transaction->update(array_merge([
            'provider_reference' => $response->providerReference ?? $transaction->provider_reference,
            'status' => $response->status,
            'reconciliation_status' => $response->reconciliationStatus,
            'failure_reason' => $response->successful ? $transaction->failure_reason : $response->message,
            'provider_payload' => $response->raw,
        ], $extra));
    }

    private function audit(string $event, MobileMoneyTransaction $transaction, array $metadata = []): void
    {
        $this->auditLogger->record(
            event: $event,
            subject: $transaction,
            metadata: $metadata,
        );
    }
}
