<?php

namespace App\Services\MobileMoney;

use App\Models\MobileMoneyTransaction;
use App\Services\AuditLogger;
use App\Services\ServiceEconomicsService;
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
        private readonly ServiceEconomicsService $economics,
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
        $this->applyProviderResponse($transaction, $response, ['last_status_checked_at' => now()]);
        $this->audit('mobile_money.status_checked', $transaction, ['response' => $response->raw]);
        $this->syncEconomics($transaction->fresh());

        return $transaction->fresh();
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyTransaction
    {
        $response = $this->providers->provider($transaction->provider)->reverse($transaction, $reason);
        $this->applyProviderResponse($transaction, $response);
        $this->audit('mobile_money.reversal.requested', $transaction, ['reason' => $reason, 'response' => $response->raw]);
        $this->syncEconomics($transaction->fresh());

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
        $this->audit('mobile_money.transaction.failed', $transaction, ['reason' => $reason, 'retryable' => $response->retryable]);
        $this->syncEconomics($transaction->fresh());

        return $transaction->fresh();
    }

    public function processWebhook(string $providerName, array $payload, array $headers = [], ?string $rawBody = null): MobileMoneyTransaction
    {
        $providerName = strtolower(trim($providerName));
        $secret = config("services.mobile_money.providers.{$providerName}.webhook_secret");
        $validSignature = $providerName === 'cpay'
            ? $this->signatureValidator->isValidCpay(
                rawBody: (string) $rawBody,
                headers: $headers,
                secret: $secret,
                replayWindowSeconds: (int) config('services.cpay.callback_replay_window_seconds', 300),
                expectedMerchantId: config('services.cpay.merchant_id'),
            )
            : $this->signatureValidator->isValid($payload, $headers, $secret);

        if (! $validSignature) {
            throw new InvalidArgumentException('Invalid mobile money webhook signature.');
        }

        $provider = $this->providers->provider($providerName);
        $response = $provider->processWebhook($payload, $headers);
        if ($response->webhookEventId) {
            $duplicate = MobileMoneyTransaction::where('webhook_event_id', $response->webhookEventId)->first();
            if ($duplicate) {
                $this->audit('mobile_money.webhook.duplicate', $duplicate, ['provider' => $providerName, 'webhook_event_id' => $response->webhookEventId]);

                return $duplicate;
            }
        }

        $transaction = $this->findWebhookTransaction($providerName, $payload, $response->providerReference);
        $this->applyProviderResponse($transaction, $response, ['webhook_event_id' => $response->webhookEventId, 'webhook_received_at' => now()]);
        $this->audit('mobile_money.webhook.processed', $transaction, ['provider' => $providerName, 'webhook_event_id' => $response->webhookEventId, 'response' => $response->raw]);
        $this->syncEconomics($transaction->fresh());

        return $transaction->fresh();
    }

    private function startTransaction(string $direction, array $attributes, ?string $providerName): MobileMoneyTransaction
    {
        $providerName ??= config('services.mobile_money.default_provider', 'cpay');
        $providerName = strtolower(trim((string) $providerName));
        $idempotencyKey = trim((string) Arr::get($attributes, 'idempotency_key', ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('A mobile money idempotency key is required.');
        }

        $amountMinor = Arr::get($attributes, 'amount_minor');
        if (! is_int($amountMinor) || $amountMinor <= 0) {
            throw new InvalidArgumentException('Mobile money amount_minor must be a positive integer.');
        }
        $phone = trim((string) Arr::get($attributes, 'phone', ''));
        if ($phone === '') {
            throw new InvalidArgumentException('A mobile money phone number is required.');
        }
        $currency = strtoupper(trim((string) Arr::get($attributes, 'currency', 'UGX')));
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Mobile money currency must be a three-letter currency code.');
        }

        [$transaction, $created] = DB::transaction(function () use (
            $direction,
            $attributes,
            $providerName,
            $idempotencyKey,
            $amountMinor,
            $phone,
            $currency,
        ) {
            $existing = MobileMoneyTransaction::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertIdempotentReplay(
                    $existing,
                    $direction,
                    $providerName,
                    $attributes,
                    $amountMinor,
                    $phone,
                    $currency,
                );
                $this->audit("mobile_money.{$direction}.duplicate", $existing, [
                    'idempotency_key' => $idempotencyKey,
                ]);

                return [$existing, false];
            }

            $metadata = Arr::except($attributes, [
                'transaction_id', 'credit_offer_id', 'loan_id', 'user_id', 'institution_id', 'provider', 'direction',
                'amount_minor', 'currency', 'phone', 'idempotency_key', 'internal_reference',
            ]);
            $metadata['instruction_fingerprint'] = $this->instructionFingerprint(
                $direction,
                $providerName,
                $attributes,
                $amountMinor,
                $phone,
                $currency,
            );
            $transaction = MobileMoneyTransaction::create([
                'transaction_id' => Arr::get($attributes, 'transaction_id'),
                'credit_offer_id' => Arr::get($attributes, 'credit_offer_id'),
                'loan_id' => Arr::get($attributes, 'loan_id'),
                'user_id' => Arr::get($attributes, 'user_id'),
                'institution_id' => Arr::get($attributes, 'institution_id'),
                'provider' => $providerName,
                'direction' => $direction,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'phone' => $phone,
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => Arr::get($attributes, 'internal_reference', (string) Str::uuid()),
                'status' => MobileMoneyTransaction::STATUS_PROCESSING,
                'provider_submission_state' => 'intent_persisted',
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
                'metadata' => $metadata,
            ]);

            $this->audit("mobile_money.{$direction}.intent_persisted", $transaction, [
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => $transaction->internal_reference,
            ]);

            return [$transaction, true];
        });

        if (! $created && $transaction->provider_submission_state !== 'intent_persisted') {
            $this->syncEconomics($transaction);

            return $transaction->fresh();
        }

        return $this->submitPersistedIntent($transaction);
    }

    private function submitPersistedIntent(MobileMoneyTransaction $transaction): MobileMoneyTransaction
    {
        [$claimed, $transaction] = DB::transaction(function () use ($transaction) {
            $locked = MobileMoneyTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->provider_submission_state !== 'intent_persisted') {
                return [false, $locked];
            }

            $locked->update([
                'provider_submission_state' => 'submission_started',
                'provider_submission_started_at' => now(),
            ]);

            return [true, $locked->fresh()];
        });

        if (! $claimed) {
            $this->syncEconomics($transaction);

            return $transaction->fresh();
        }

        $direction = (string) $transaction->direction;
        $providerName = strtolower((string) $transaction->provider);

        try {
            $provider = $this->providers->provider($providerName);
        } catch (InvalidArgumentException $exception) {
            $transaction->refresh();
            $transaction->update([
                'status' => MobileMoneyTransaction::STATUS_FAILED,
                'provider_submission_state' => 'rejected_before_provider_finality',
                'provider_submission_resolved_at' => now(),
                'accounting_status' => MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                'failure_reason' => $exception->getMessage(),
            ]);
            $this->audit("mobile_money.{$direction}.submission_rejected", $transaction, [
                'reason' => $exception->getMessage(),
            ]);
            $this->syncEconomics($transaction->fresh());

            throw $exception;
        }

        try {
            $response = $direction === MobileMoneyTransaction::DIRECTION_DISBURSEMENT
                ? $provider->disburse($transaction->fresh())
                : $provider->collect($transaction->fresh());

            $this->applyProviderResponse($transaction, $response);
            $this->audit("mobile_money.{$direction}.provider_response", $transaction, [
                'response' => $response->raw,
            ]);
        } catch (\Throwable $exception) {
            $transaction->refresh();
            $transaction->update([
                'provider_submission_state' => 'ambiguous',
                'failure_reason' => 'Provider submission outcome is ambiguous. Preserve this intent and reconcile by canonical reference before any retry.',
            ]);
            $this->audit("mobile_money.{$direction}.submission_ambiguous", $transaction, [
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->syncEconomics($transaction->fresh());

            throw $exception;
        }

        $this->syncEconomics($transaction->fresh());

        return $transaction->fresh();
    }

    private function assertIdempotentReplay(
        MobileMoneyTransaction $existing,
        string $direction,
        string $providerName,
        array $attributes,
        int $amountMinor,
        string $phone,
        string $currency,
    ): void {
        $fingerprint = $this->instructionFingerprint($direction, $providerName, $attributes, $amountMinor, $phone, $currency);
        $existingMetadata = is_array($existing->metadata) ? $existing->metadata : [];
        $storedFingerprint = $existingMetadata['instruction_fingerprint'] ?? null;
        if (is_string($storedFingerprint) && $storedFingerprint !== '' && ! hash_equals($storedFingerprint, $fingerprint)) {
            throw new InvalidArgumentException('The supplied idempotency key was already used for a different canonical money instruction.');
        }

        $comparisons = [
            'provider' => [strtolower((string) $existing->provider), $providerName],
            'direction' => [(string) $existing->direction, $direction],
            'amount_minor' => [(int) $existing->amount_minor, $amountMinor],
            'currency' => [strtoupper((string) $existing->currency), $currency],
            'phone' => [trim((string) $existing->phone), $phone],
            'transaction_id' => [$existing->transaction_id ? (int) $existing->transaction_id : null, Arr::get($attributes, 'transaction_id') ? (int) Arr::get($attributes, 'transaction_id') : null],
            'credit_offer_id' => [$existing->credit_offer_id ? (int) $existing->credit_offer_id : null, Arr::get($attributes, 'credit_offer_id') ? (int) Arr::get($attributes, 'credit_offer_id') : null],
            'loan_id' => [$existing->loan_id ? (int) $existing->loan_id : null, Arr::get($attributes, 'loan_id') ? (int) Arr::get($attributes, 'loan_id') : null],
            'user_id' => [$existing->user_id ? (int) $existing->user_id : null, Arr::get($attributes, 'user_id') ? (int) Arr::get($attributes, 'user_id') : null],
            'institution_id' => [$existing->institution_id ? (int) $existing->institution_id : null, Arr::get($attributes, 'institution_id') ? (int) Arr::get($attributes, 'institution_id') : null],
        ];

        $requestedInternalReference = Arr::get($attributes, 'internal_reference');
        if ($requestedInternalReference !== null) {
            $comparisons['internal_reference'] = [(string) $existing->internal_reference, (string) $requestedInternalReference];
        }

        foreach (['purpose', 'source_type', 'source_id', 'savings_movement_id', 'protection_premium_payment_id'] as $key) {
            if (array_key_exists($key, $attributes)) {
                $comparisons[$key] = [$existingMetadata[$key] ?? null, $attributes[$key]];
            }
        }

        foreach ($comparisons as $field => [$original, $requested]) {
            if ($original !== $requested) {
                throw new InvalidArgumentException("The supplied idempotency key was already used for a different money movement ({$field} mismatch).");
            }
        }
    }

    private function instructionFingerprint(
        string $direction,
        string $providerName,
        array $attributes,
        int $amountMinor,
        string $phone,
        string $currency,
    ): string {
        $instruction = [
            'provider' => $providerName,
            'direction' => $direction,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'phone' => $phone,
            'transaction_id' => Arr::get($attributes, 'transaction_id'),
            'credit_offer_id' => Arr::get($attributes, 'credit_offer_id'),
            'loan_id' => Arr::get($attributes, 'loan_id'),
            'user_id' => Arr::get($attributes, 'user_id'),
            'institution_id' => Arr::get($attributes, 'institution_id'),
            'internal_reference' => Arr::get($attributes, 'internal_reference'),
            'purpose' => Arr::get($attributes, 'purpose'),
            'source_type' => Arr::get($attributes, 'source_type'),
            'source_id' => Arr::get($attributes, 'source_id'),
            'savings_movement_id' => Arr::get($attributes, 'savings_movement_id'),
            'protection_premium_payment_id' => Arr::get($attributes, 'protection_premium_payment_id'),
            'early_settlement_quote_id' => Arr::get($attributes, 'early_settlement_quote_id'),
            'billing_invoice_id' => Arr::get($attributes, 'billing_invoice_id'),
        ];

        return hash('sha256', json_encode($instruction, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function findWebhookTransaction(string $providerName, array $payload, ?string $providerReference): MobileMoneyTransaction
    {
        if ($providerName !== 'cpay') {
            return MobileMoneyTransaction::where('provider', $providerName)
                ->where('provider_reference', $providerReference)
                ->firstOrFail();
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $merchantReference = $payload['reference'] ?? $payload['merchantReference'] ?? $payload['merchant_reference'] ?? $data['merchantReference'] ?? $data['merchant_reference'] ?? null;
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? $providerReference;

        $byProviderReference = $transactionId
            ? MobileMoneyTransaction::where('provider', $providerName)->where('provider_reference', $transactionId)->first()
            : null;
        $byMerchantReference = $merchantReference
            ? MobileMoneyTransaction::where('provider', $providerName)->where('internal_reference', $merchantReference)->first()
            : null;

        if ($byProviderReference && $byMerchantReference && $byProviderReference->id !== $byMerchantReference->id) {
            throw new InvalidArgumentException('CPay webhook identifiers resolve to different money movements.');
        }

        $transaction = $byProviderReference ?: $byMerchantReference;
        if (! $transaction) {
            throw new InvalidArgumentException('CPay webhook could not be matched to an OpFin money movement.');
        }

        return $transaction;
    }

    private function assertProviderTransition(MobileMoneyTransaction $transaction, string $nextStatus): void
    {
        if (strtolower((string) $transaction->provider) !== 'cpay') {
            return;
        }

        $allowed = match ($transaction->status) {
            MobileMoneyTransaction::STATUS_PROCESSING, MobileMoneyTransaction::STATUS_PENDING => [
                MobileMoneyTransaction::STATUS_PROCESSING,
                MobileMoneyTransaction::STATUS_PENDING,
                MobileMoneyTransaction::STATUS_SUCCESSFUL,
                MobileMoneyTransaction::STATUS_FAILED,
                MobileMoneyTransaction::STATUS_REVERSED,
            ],
            MobileMoneyTransaction::STATUS_SUCCESSFUL => [
                MobileMoneyTransaction::STATUS_SUCCESSFUL,
                MobileMoneyTransaction::STATUS_REVERSED,
            ],
            MobileMoneyTransaction::STATUS_FAILED => [MobileMoneyTransaction::STATUS_FAILED],
            MobileMoneyTransaction::STATUS_REVERSED => [MobileMoneyTransaction::STATUS_REVERSED],
            default => [],
        };

        if (! in_array($nextStatus, $allowed, true)) {
            throw new InvalidArgumentException("CPay status transition {$transaction->status} -> {$nextStatus} is not allowed.");
        }
    }

    private function applyProviderResponse(MobileMoneyTransaction $transaction, MobileMoneyProviderResponse $response, array $extra = []): void
    {
        DB::transaction(function () use ($transaction, $response, $extra) {
            $locked = MobileMoneyTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertProviderTransition($locked, $response->status);

            $previousProviderReference = $locked->provider_reference;
            $nextProviderReference = $response->providerReference ?? $locked->provider_reference;
            $providerEvidenceChanged = $locked->status !== $response->status
                || (string) ($previousProviderReference ?? '') !== (string) ($nextProviderReference ?? '');

            $statementStatus = $providerEvidenceChanged
                ? MobileMoneyTransaction::STATEMENT_UNRECONCILED
                : $locked->statement_reconciliation_status;
            $statementReconciledAt = $providerEvidenceChanged ? null : $locked->statement_reconciled_at;
            $combinedStatus = $statementStatus === MobileMoneyTransaction::STATEMENT_MATCHED
                ? MobileMoneyTransaction::RECONCILIATION_MATCHED
                : MobileMoneyTransaction::RECONCILIATION_PENDING;

            $locked->update(array_merge([
                'provider_reference' => $nextProviderReference,
                'status' => $response->status,
                'provider_submission_state' => 'provider_response_received',
                'provider_submission_resolved_at' => now(),
                'statement_reconciliation_status' => $statementStatus,
                'statement_reconciled_at' => $statementReconciledAt,
                'reconciliation_status' => $combinedStatus,
                'failure_reason' => $response->successful ? $locked->failure_reason : $response->message,
                'provider_payload' => $response->raw,
            ], $extra));

            if ($providerEvidenceChanged) {
                $references = array_values(array_filter(array_unique([
                    $previousProviderReference,
                    $nextProviderReference,
                ])));
                if ($references !== []) {
                    DB::table('revenue_events')->whereIn('cpay_reference', $references)->update([
                        'statement_reconciliation_status' => 'unreconciled',
                        'reconciliation_reference' => null,
                        'updated_at' => now(),
                    ]);
                }
            }

            $transaction->setRawAttributes($locked->fresh()->getAttributes(), true);
        });
    }

    private function syncEconomics(MobileMoneyTransaction $transaction): void
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $provider = strtolower((string) $transaction->provider);
        $statementMatched = $transaction->statement_reconciliation_status === MobileMoneyTransaction::STATEMENT_MATCHED;

        $this->economics->record([
            'user_id' => $transaction->user_id,
            'service_code' => 'payments',
            'capability_code' => (string) $transaction->direction,
            'provider' => $provider,
            'route' => $provider === 'cpay' ? 'CITO_MANAGED' : 'DIRECT_PROVIDER',
            'environment' => $provider === 'cpay'
                ? strtoupper((string) config('services.cpay.environment', 'SANDBOX'))
                : (app()->environment('production') ? 'PRODUCTION' : 'SANDBOX'),
            'request_reference' => 'money:'.(string) $transaction->idempotency_key,
            'provider_reference' => $transaction->provider_reference,
            'status' => strtoupper((string) $transaction->status),
            'currency' => (string) $transaction->currency,
            'metadata' => [
                'mobile_money_transaction_id' => $transaction->id,
                'money_movement_amount_minor' => (int) $transaction->amount_minor,
                'money_movement_amount_is_not_platform_revenue' => true,
                'internal_reference' => $transaction->internal_reference,
                'purpose' => $metadata['purpose'] ?? null,
                'source_type' => $metadata['source_type'] ?? null,
                'source_id' => $metadata['source_id'] ?? null,
                'accounting_status' => $transaction->accounting_status,
                'statement_reconciliation_status' => $transaction->statement_reconciliation_status,
                'combined_reconciliation_status' => $transaction->reconciliation_status,
            ],
            'occurred_at' => $transaction->created_at ?? now(),
            'reconciliation_reference' => $statementMatched ? $transaction->provider_reference : null,
            'reconciled_at' => $statementMatched ? $transaction->statement_reconciled_at : null,
        ]);
    }

    private function audit(string $event, MobileMoneyTransaction $transaction, array $metadata = []): void
    {
        $this->auditLogger->record(event: $event, subject: $transaction, metadata: $metadata);
    }
}
