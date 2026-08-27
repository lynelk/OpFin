<?php

namespace Tests\Feature;

use App\Models\MobileMoneyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpayWebhookReplaySafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.cpay.callback_secret', 'cpay-callback-secret');
        config()->set('services.cpay.merchant_id', '77');
        config()->set('services.cpay.callback_replay_window_seconds', 300);
        config()->set('services.mobile_money.providers.cpay.webhook_secret', 'cpay-callback-secret');
    }

    public function test_cpay_callback_rejects_reused_delivery_nonce_within_replay_window(): void
    {
        $this->createTransaction('opfin-replay-1', 'cpay-replay-tx-1', 'cpay-replay-key-1');
        $payload = $this->successfulPayload('evt-replay-1', 'opfin-replay-1', 'cpay-replay-tx-1');
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->timestamp;
        $headers = $this->signedHeaders($rawBody, $timestamp, 'nonce-reused-1', '101', 'opfin-replay-1');

        $this->postRawCallback($rawBody, $headers)->assertOk();
        $this->postRawCallback($rawBody, $headers)->assertUnauthorized();

        $this->assertDatabaseCount('cpay_webhook_nonces', 1);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'internal_reference' => 'opfin-replay-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'webhook_event_id' => 'evt-replay-1',
        ]);
    }

    public function test_duplicate_event_with_fresh_delivery_nonce_remains_idempotently_acceptable(): void
    {
        $this->createTransaction('opfin-retry-1', 'cpay-retry-tx-1', 'cpay-retry-key-1');
        $payload = $this->successfulPayload('evt-retry-1', 'opfin-retry-1', 'cpay-retry-tx-1');
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->timestamp;

        $first = $this->signedHeaders($rawBody, $timestamp, 'nonce-delivery-1', '102', 'opfin-retry-1');
        $second = $this->signedHeaders($rawBody, $timestamp, 'nonce-delivery-2', '102', 'opfin-retry-1');

        $this->postRawCallback($rawBody, $first)->assertOk();
        $this->postRawCallback($rawBody, $second)->assertOk();

        $this->assertDatabaseCount('cpay_webhook_nonces', 2);
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'internal_reference' => 'opfin-retry-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'webhook_event_id' => 'evt-retry-1',
        ]);
    }

    public function test_cpay_callback_rejects_expired_signed_delivery_before_consuming_nonce(): void
    {
        $payload = $this->successfulPayload('evt-expired-1', 'opfin-expired-1', 'cpay-expired-tx-1');
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->subSeconds(301)->timestamp;
        $headers = $this->signedHeaders($rawBody, $timestamp, 'nonce-expired-1', '103', 'opfin-expired-1');

        $this->postRawCallback($rawBody, $headers)->assertUnauthorized();
        $this->assertDatabaseCount('cpay_webhook_nonces', 0);
    }

    private function createTransaction(string $reference, string $providerReference, string $idempotencyKey): void
    {
        MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000002',
            'idempotency_key' => $idempotencyKey,
            'internal_reference' => $reference,
            'provider_reference' => $providerReference,
            'status' => MobileMoneyTransaction::STATUS_PENDING,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);
    }

    private function successfulPayload(string $eventId, string $merchantReference, string $transactionId): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => 'payment.succeeded',
            'event_version' => 1,
            'merchant_id' => '77',
            'data' => [
                'transaction_id' => $transactionId,
                'merchant_reference' => $merchantReference,
                'status' => 'SUCCESSFUL',
                'amount' => ['currency' => 'UGX', 'minor_units' => 50000],
            ],
        ];
    }

    private function signedHeaders(
        string $rawBody,
        string $timestamp,
        string $nonce,
        string $taskId,
        string $reference,
    ): array {
        $canonical = implode("\n", [
            $taskId,
            '77',
            $reference,
            $timestamp,
            $nonce,
            $rawBody,
        ]);
        $signature = base64_encode(hash_hmac('sha256', $canonical, 'cpay-callback-secret', true));

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CPAY_SIGNATURE_VERSION' => 'callback-v1',
            'HTTP_X_CPAY_SIGNATURE' => $signature,
            'HTTP_X_CPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_CPAY_NONCE' => $nonce,
            'HTTP_X_CPAY_CALLBACK_TASK_ID' => $taskId,
            'HTTP_X_CPAY_MERCHANT_ID' => '77',
            'HTTP_X_CPAY_REFERENCE' => $reference,
        ];
    }

    private function postRawCallback(string $rawBody, array $headers)
    {
        return $this->call(
            'POST',
            '/api/webhooks/cpay',
            [],
            [],
            [],
            $headers,
            $rawBody,
        );
    }
}
