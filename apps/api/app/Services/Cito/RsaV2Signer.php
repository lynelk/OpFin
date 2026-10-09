<?php

namespace App\Services\Cito;

use RuntimeException;

/**
 * Shared RSA-SHA256 CPay/Cito v2 signing primitive.
 * The caller supplies the exact canonical query and exact transmitted body.
 */
final class RsaV2Signer
{
    public function canonical(string $method, string $path, string $canonicalQuery, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [
            strtoupper($method), $path, $canonicalQuery,
            $timestamp, $nonce, hash('sha256', $body),
        ]);
    }

    public function sign(string $canonical, string $pem): string
    {
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", $pem));
        if ($key === false || ! openssl_sign($canonical, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Cito/CPay RSA v2 signing failed.');
        }
        return base64_encode($signature);
    }
}
