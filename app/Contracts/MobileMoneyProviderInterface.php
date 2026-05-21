<?php

namespace App\Contracts;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\MobileMoneyProviderResponse;

interface MobileMoneyProviderInterface
{
    public function disburse(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse;

    public function collect(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse;

    public function lookupStatus(MobileMoneyTransaction $transaction): MobileMoneyProviderResponse;

    public function processWebhook(array $payload, array $headers = []): MobileMoneyProviderResponse;

    public function reverse(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse;

    public function handleFailure(MobileMoneyTransaction $transaction, string $reason): MobileMoneyProviderResponse;
}
