<?php

namespace App\Services\MobileMoney;

use Carbon\CarbonImmutable;

class WebhookSignatureValidator
{
    public function isValid(array $payload, array $headers, ?string $secret): bool
    {
        if (!$secret) {
            return false;
        }

        $signature = $this->header($headers, 'X-Opfin-Mobile-Money-Signature')
            ?? $this->header($headers, 'X-Momo-Signature')
            ?? $this->header($headers, 'X-Airtel-Signature');

        if (!is_string($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }

    public function isValidCpay(
        string $rawBody,
        array $headers,
        ?string $secret,
        int $replayWindowSeconds = 300,
        ?string $expectedMerchantId = null,
    ): bool {
        if (!$secret || $rawBody === '') {
            return false;
        }

        $version = $this->header($headers, 'X-CPay-Signature-Version');
        $signature = $this->header($headers, 'X-CPay-Signature');
        $timestamp = $this->header($headers, 'X-CPay-Timestamp');
        $nonce = $this->header($headers, 'X-CPay-Nonce');
        $taskId = $this->header($headers, 'X-CPay-Callback-Task-Id');
        $merchantId = $this->header($headers, 'X-CPay-Merchant-Id');
        $reference = $this->header($headers, 'X-CPay-Reference');

        if ($version !== 'callback-v1'
            || !$signature
            || !$timestamp
            || !$nonce
            || !$taskId
            || !$merchantId
            || $reference === null) {
            return false;
        }

        if ($expectedMerchantId !== null && !hash_equals($expectedMerchantId, $merchantId)) {
            return false;
        }

        if (!ctype_digit($timestamp)) {
            return false;
        }

        $age = abs(CarbonImmutable::now('UTC')->timestamp - (int) $timestamp);
        if ($age > $replayWindowSeconds) {
            return false;
        }

        $canonical = implode("\n", [
            $taskId,
            $merchantId,
            $reference,
            $timestamp,
            $nonce,
            $rawBody,
        ]);
        $expected = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

        return hash_equals($expected, $signature);
    }

    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) !== 0) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }
}
