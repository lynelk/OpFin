<?php

namespace App\Services\MobileMoney;

class WebhookSignatureValidator
{
    public function isValid(array $payload, array $headers, ?string $secret): bool
    {
        if (!$secret) {
            return false;
        }

        $signature = $headers['X-Opfin-Mobile-Money-Signature']
            ?? $headers['x-opfin-mobile-money-signature']
            ?? $headers['X-Momo-Signature']
            ?? $headers['X-Airtel-Signature']
            ?? null;

        if (!is_string($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }
}
