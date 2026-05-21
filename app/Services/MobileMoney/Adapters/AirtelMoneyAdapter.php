<?php

namespace App\Services\MobileMoney\Adapters;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;

class AirtelMoneyAdapter implements MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('Airtel disbursement adapter is not implemented; no live call was made.');
    }

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('Airtel collection adapter is not implemented; no live call was made.');
    }

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse
    {
        return $this->notImplemented('Airtel status lookup adapter is not implemented; no live call was made.');
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
        return $this->notImplemented('Airtel reversal adapter is not implemented; no live call was made.');
    }

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('airtel', $reason, false, ['reason' => $reason]);
    }

    private function notImplemented(string $message): MobileMoneyProviderResponse
    {
        return MobileMoneyProviderResponse::failed('airtel', $message, false);
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
