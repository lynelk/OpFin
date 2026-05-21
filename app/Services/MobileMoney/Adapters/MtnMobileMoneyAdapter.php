<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;

class MtnMobileMoneyAdapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('MTN disbursement adapter is not implemented; no live call was made.');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('MTN collection adapter is not implemented; no live call was made.');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('MTN status lookup adapter is not implemented; no live call was made.');
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
        return $this->notImplemented('MTN reversal adapter is not implemented; no live call was made.');
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('mtn', $reason, false, ['reason' => $reason]);
    }

    private function notImplemented(string $message): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('mtn', $message, false);
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
