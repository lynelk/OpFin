<?php

namespace Tests\Unit;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CpayV2RetrySafetyTest extends TestCase
{
    public function test_connection_failure_does_not_automatically_resubmit_a_financial_request(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', $privateKey);
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/cpay/webhook');
        Config::set('services.cpay.environment', 'sandbox');
        Config::set('services.cpay.connect_retries', 5);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('Simulated unknown financial outcome');
        });

        $transaction = new MobileMoneyTransaction();
        $transaction->internal_reference = 'retry-safety-1';
        $transaction->idempotency_key = 'retry-safety-1';
        $transaction->direction = 'collection';
        $transaction->currency = 'UGX';
        $transaction->amount_minor = 5000;
        $transaction->phone = '256700000001';
        $transaction->metadata = [];

        try {
            (new CpayV2Adapter())->collect($transaction);
            $this->fail('Connection failure must propagate for reconciliation.');
        } catch (ConnectionException $exception) {
            $this->assertSame(1, $attempts, 'Never automatically resubmit signed money movement.');
        }
    }
}
