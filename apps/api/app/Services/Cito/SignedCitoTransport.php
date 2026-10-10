<?php

namespace App\Services\Cito;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Single-attempt Cito RSA v2 transport. Side effects are never retried implicitly.
 * Recovery must first reconcile the original business reference.
 */
class SignedCitoTransport
{
    public function request(string $method, string $path, array $payload = [], array $query = [], ?string $idempotencyKey = null): Response
    {
        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST'], true) || ! preg_match('#^/api/v2/[A-Za-z0-9_./{}%-]+$#', $path)) {
            throw new InvalidArgumentException('Unsupported Cito request method or path.');
        }
        $base = trim((string) config('services.cito.base_url'));
        $merchant = trim((string) config('services.cito.merchant_number'));
        $privateKey = str_replace('\\n', "\n", (string) config('services.cito.private_key'));
        $environment = strtoupper(trim((string) config('services.cito.environment', 'SANDBOX')));
        $origin = parse_url($base);
        if (! is_array($origin) || ($origin['scheme'] ?? '') !== 'https' || empty($origin['host'])
            || isset($origin['user']) || isset($origin['pass']) || isset($origin['query']) || isset($origin['fragment'])
            || $merchant === '' || $privateKey === '' || ! in_array($environment, ['SANDBOX', 'PRODUCTION'], true)) {
            throw new InvalidArgumentException('Approved Cito HTTPS origin, merchant credentials and environment are required.');
        }
        if ($method === 'GET' && $payload !== []) {
            throw new InvalidArgumentException('GET must not have a request body.');
        }
        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC1738);
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->format('Y-m-d\\TH:i:s\\Z');
        $nonce = (string) Str::uuid();
        $signer = app(RsaV2Signer::class);
        $canonical = $signer->canonical($method, $path, $canonicalQuery, $timestamp, $nonce, $body);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CPay-Merchant-Number' => $merchant,
            'X-CPay-Signature-Version' => 'v2',
            'X-CPay-Timestamp' => $timestamp,
            'X-CPay-Nonce' => $nonce,
            'X-CPay-Signature' => $signer->sign($canonical, $privateKey),
            'X-CPay-Environment' => $environment,
        ];
        if ($idempotencyKey !== null) {
            if (trim($idempotencyKey) === '') {
                throw new InvalidArgumentException('Empty idempotency key.');
            }
            $headers['X-CPay-Idempotency-Key'] = $idempotencyKey;
        }
        $url = rtrim($base, '/').$path.($canonicalQuery === '' ? '' : '?'.$canonicalQuery);
        // Disallow redirect following and automatic retries for signed operations.
        $request = Http::withOptions(['allow_redirects' => false])
            ->withHeaders($headers)->timeout((int) config('services.cito.timeout_seconds', 15));

        return $request->withBody($body, 'application/json')->send($method, $url);
    }
}
