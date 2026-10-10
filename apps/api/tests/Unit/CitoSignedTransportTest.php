<?php

namespace Tests\Unit;

use App\Services\Cito\SignedCitoTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class CitoSignedTransportTest extends TestCase
{
    public function test_signed_request_is_verified_against_exact_bytes_and_uses_no_automatic_retry(): void
    {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        $public = openssl_pkey_get_details($pair)['key'];
        Config::set('services.cito.base_url', 'https://cito.example.test');
        Config::set('services.cito.merchant_number', 'OPFIN-1');
        Config::set('services.cito.private_key', $private);
        Config::set('services.cito.environment', 'SANDBOX');
        Http::fake(['https://cito.example.test/*' => Http::response(['accepted' => true], 202)]);

        app(SignedCitoTransport::class)->request('POST', '/api/v2/credit/scores', ['a' => 'b'], [], 'stable-1');

        Http::assertSent(function ($request) use ($public) {
            $canonical = implode("\n", [
                'POST', '/api/v2/credit/scores', '',
                $request->header('X-CPay-Timestamp')[0],
                $request->header('X-CPay-Nonce')[0],
                hash('sha256', $request->body()),
            ]);

            return $request->hasHeader('X-CPay-Idempotency-Key', 'stable-1')
                && $request->hasHeader('X-CPay-Environment', 'SANDBOX')
                && openssl_verify(
                    $canonical,
                    base64_decode($request->header('X-CPay-Signature')[0], true),
                    $public,
                    OPENSSL_ALGO_SHA256
                ) === 1;
        });
    }

    public function test_rejects_non_https_credential_origin(): void
    {
        Config::set('services.cito.base_url', 'http://cito.example.test');
        Config::set('services.cito.merchant_number', 'OPFIN-1');
        Config::set('services.cito.private_key', 'test-key');
        $this->expectException(InvalidArgumentException::class);
        app(SignedCitoTransport::class)->request('GET', '/api/v2/capabilities');
    }
}
