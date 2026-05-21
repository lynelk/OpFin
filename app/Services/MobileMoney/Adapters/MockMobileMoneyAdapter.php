<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;

class MockMobileMoneyAdapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->accepted($transaction, 'Mock disbursement accepted');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->accepted($transaction, 'Mock collection accepted');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return new MobileMoneyProviderResponse(
            provider: 'mock',
            successful: true,
            status: $transaction->status,
            providerReference: $transaction->provider_reference,
            message: 'Mock status returned',
            retryable: $transaction->status === MobileMoneyTransaction::STATUS_PENDING,
            reconciliationStatus: $transaction->reconciliation_status,
            raw: ['mock' => true, 'status' => $transaction->status],
        );
    }

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse
    {
        return new MobileMoneyProviderResponse(
            provider: 'mock',
            successful: ($payload['status'] ?? null) === MobileMoneyTransaction::STATUS_SUCCESSFUL,
            status: $payload['status'] ?? MobileMoneyTransaction::STATUS_FAILED,
            providerReference: $payload['provider_reference'] ?? null,
            message: $payload['message'] ?? 'Mock webhook processed',
            retryable: false,
            reconciliationStatus: ($payload['status'] ?? null) === MobileMoneyTransaction::STATUS_SUCCESSFUL
                ? MobileMoneyTransaction::RECONCILIATION_PENDING
                : MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
            webhookEventId: $payload['event_id'] ?? null,
            raw: $payload,
        );
    }

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return new MobileMoneyProviderResponse(
            provider: 'mock',
            successful: true,
            status: MobileMoneyTransaction::STATUS_REVERSED,
            providerReference: $transaction->provider_reference,
            message: 'Mock reversal accepted',
            retryable: false,
            reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
            raw: ['mock' => true, 'reason' => $reason],
        );
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed(
            provider: 'mock',
            message: $reason,
            retryable: $transaction->retry_count + 1 < $transaction->max_retries,
            raw: ['mock' => true, 'reason' => $reason],
        );
    }

    private function accepted(MobileMoneyTransaction $transaction, string $message): MobileMoneyProviderResponse
    {
        $reference = 'mock-' . $transaction->internal_reference;

        return MobileMoneyProviderResponse::pending(
            provider: 'mock',
            providerReference: $reference,
            message: $message,
            raw: [
                'mock' => true,
                'provider_reference' => $reference,
                'internal_reference' => $transaction->internal_reference,
            ],
        );
    }
}
