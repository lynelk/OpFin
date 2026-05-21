<?php

namespace App\Services\MobileMoney;

use App\Models\MobileMoneyTransaction;

class MobileMoneyProviderResponse
{
    public function __construct(
        public readonly string $provider,
        public readonly bool $successful,
        public readonly string $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $message = null,
        public readonly bool $retryable = false,
        public readonly string $reconciliationStatus = MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
        public readonly ?string $webhookEventId = null,
        public readonly array $raw = [],
    ) {}

    public static function pending(
        string $provider,
        ?string $providerReference,
        string $message = 'Request accepted',
        array $raw = [],
    ): self {
        return new self(
            provider: $provider,
            successful: true,
            status: MobileMoneyTransaction::STATUS_PENDING,
            providerReference: $providerReference,
            message: $message,
            retryable: true,
            reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
            raw: $raw,
        );
    }

    public static function failed(
        string $provider,
        string $message,
        bool $retryable = false,
        array $raw = [],
    ): self {
        return new self(
            provider: $provider,
            successful: false,
            status: MobileMoneyTransaction::STATUS_FAILED,
            message: $message,
            retryable: $retryable,
            reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_EXCEPTION,
            raw: $raw,
        );
    }
}
