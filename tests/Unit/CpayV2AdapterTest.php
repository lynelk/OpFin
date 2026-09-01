<?php

namespace Tests\Unit;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class CpayV2AdapterTest extends TestCase
{
    public function test_adapter_requires_cpay_configuration(): void
    {
        Config::set('services.cpay.base_url', null);
        Config::set('services.cpay.merchant_number', null);
        Config::set('services.cpay.private_key', null);
        Config::set('services.cpay.callback_url', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CPay base_url is not configured.');

        (new CpayV2Adapter)->collect($this->transaction());
    }

    public function test_collection_uses_cpay_v2_rsa_signature_idempotency_environment_and_trace_contract(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', $privateKey);
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/cpay/webhook');
        Config::set('services.cpay.country', 'UG');
        Config::set('services.cpay.environment', 'sandbox');
        Config::set('services.cpay.minor_unit_exponent', 0);
        Config::set('services.cpay.connect_retries', 0);

        Http::fake([
            'https://cpay.example.test/api/v2/native/payments/collect' => Http::response([
                'reference' => 'opfin-ref-1',
                'transactionId' => 'cpay-tx-1',
                'status' => 'PENDING',
                'currency' => 'UGX',
                'message' => 'Accepted',
            ], 202),
        ]);

        $response = (new CpayV2Adapter)->collect($this->transaction());

        $this->assertTrue($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_PENDING, $response->status);
        $this->assertSame('cpay-tx-1', $response->providerReference);

        Http::assertSent(function ($request) use ($publicKey): bool {
            $timestamp = $request->header('X-CPay-Timestamp')[0] ?? '';
            $nonce = $request->header('X-CPay-Nonce')[0] ?? '';
            $signature = $request->header('X-CPay-Signature')[0] ?? '';
            $body = $request->body();
            $canonical = implode("\n", [
                'POST',
                '/api/v2/native/payments/collect',
                '',
                $timestamp,
                $nonce,
                hash('sha256', $body),
            ]);

            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $verified = openssl_verify(
                $canonical,
                base64_decode($signature, true),
                $publicKey,
                OPENSSL_ALGO_SHA256,
            );

            return $request->url() === 'https://cpay.example.test/api/v2/native/payments/collect'
                && $request->hasHeader('X-CPay-Merchant-Number', 'OPFIN-001')
                && $request->hasHeader('X-CPay-Signature-Version', 'v2')
                && $request->hasHeader('X-CPay-Idempotency-Key', 'idem-1')
                && $request->hasHeader('X-CPay-Environment', 'sandbox')
                && $payload['merchantNumber'] === 'OPFIN-001'
                && $payload['amount'] === '100000'
                && $payload['reference'] === 'opfin-ref-1'
                && $payload['payer']['value'] === '256700000001'
                && $payload['metadata']['correlationId'] === 'corr-1'
                && $payload['metadata']['traceId'] === 'trace-1'
                && $payload['metadata']['productReference'] === 'loan-42'
                && $payload['metadata']['customerReference'] === 'customer-9'
                && $payload['metadata']['purpose'] === 'loan_repayment'
                && $verified === 1;
        });
    }

    public function test_current_cpay_camel_case_completed_webhook_is_normalized(): void
    {
        $response = (new CpayV2Adapter)->processWebhook([
            'eventId' => 'evt-1',
            'eventType' => 'payment.completed',
            'eventVersion' => '1.0',
            'merchantNumber' => 'OPFIN-001',
            'reference' => 'opfin-ref-1',
            'transactionId' => 'cpay-tx-1',
            'status' => 'SUCCESSFUL',
        ]);

        $this->assertTrue($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_SUCCESSFUL, $response->status);
        $this->assertSame('cpay-tx-1', $response->providerReference);
        $this->assertSame('evt-1', $response->webhookEventId);
        $this->assertFalse($response->retryable);
    }

    public function test_failed_cpay_webhook_is_terminal_and_not_retryable(): void
    {
        $response = (new CpayV2Adapter)->processWebhook([
            'eventId' => 'evt-2',
            'eventType' => 'payout.failed',
            'reference' => 'opfin-ref-2',
            'transactionId' => 'cpay-tx-2',
        ]);

        $this->assertFalse($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_FAILED, $response->status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_MATCHED, $response->reconciliationStatus);
        $this->assertFalse($response->retryable);
    }

    public function test_status_lookup_signs_canonical_query(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', $privateKey);
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/cpay/webhook');
        Config::set('services.cpay.connect_retries', 0);

        Http::fake([
            'https://cpay.example.test/api/v2/payments/opfin-ref-1?merchantNumber=OPFIN-001' => Http::response([
                'reference' => 'opfin-ref-1',
                'transactionId' => 'cpay-tx-1',
                'status' => 'SUCCESSFUL',
            ], 200),
        ]);

        $response = (new CpayV2Adapter)->lookupStatus($this->transaction());

        $this->assertTrue($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_SUCCESSFUL, $response->status);

        Http::assertSent(function ($request) use ($publicKey): bool {
            $timestamp = $request->header('X-CPay-Timestamp')[0] ?? '';
            $nonce = $request->header('X-CPay-Nonce')[0] ?? '';
            $signature = $request->header('X-CPay-Signature')[0] ?? '';
            $canonical = implode("\n", [
                'GET',
                '/api/v2/payments/opfin-ref-1',
                'merchantNumber=OPFIN-001',
                $timestamp,
                $nonce,
                hash('sha256', ''),
            ]);

            return openssl_verify(
                $canonical,
                base64_decode($signature, true),
                $publicKey,
                OPENSSL_ALGO_SHA256,
            ) === 1;
        });
    }

    private function transaction(): MobileMoneyTransaction
    {
        return new MobileMoneyTransaction([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 100000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'idempotency_key' => 'idem-1',
            'internal_reference' => 'opfin-ref-1',
            'status' => MobileMoneyTransaction::STATUS_PROCESSING,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
            'metadata' => [
                'description' => 'OpFin repayment',
                'correlation_id' => 'corr-1',
                'trace_id' => 'trace-1',
                'product_reference' => 'loan-42',
                'customer_reference' => 'customer-9',
                'purpose' => 'loan_repayment',
            ],
        ]);
    }

    private function keyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($resource);

        $privateKey = '';
        $this->assertTrue(openssl_pkey_export($resource, $privateKey));
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);

        return [$privateKey, $details['key']];
    }
}
