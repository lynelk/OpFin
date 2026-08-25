<?php

namespace Tests\Feature;

use App\Models\MobileMoneyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_cpay_callback_updates_matching_transaction(): void
    {
        config()->set('services.cpay.callback_secret', 'cpay-callback-secret');
        config()->set('services.cpay.merchant_id', '77');
        config()->set('services.cpay.callback_replay_window_seconds', 300);
        config()->set('services.mobile_money.providers.cpay.webhook_secret', 'cpay-callback-secret');

        MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000002',
            'idempotency_key' => 'cpay-collection-1',
            'internal_reference' => 'opfin-ref-1',
            'provider_reference' => 'cpay-tx-1',
            'status' => MobileMoneyTransaction::STATUS_PENDING,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        $payload = [
            'event_id' => 'evt-opfin-1',
            'event_type' => 'payment.succeeded',
            'event_version' => 1,
            'merchant_id' => '77',
            'data' => [
                'transaction_id' => 'cpay-tx-1',
                'merchant_reference' => 'opfin-ref-1',
                'status' => 'SUCCESSFUL',
                'amount' => ['currency' => 'UGX', 'minor_units' => 50000],
            ],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->timestamp;
        $nonce = 'callback-nonce-1';
        $taskId = '42';
        $merchantId = '77';
        $reference = 'opfin-ref-1';
        $canonical = implode("\n", [
            $taskId,
            $merchantId,
            $reference,
            $timestamp,
            $nonce,
            $rawBody,
        ]);
        $signature = base64_encode(hash_hmac('sha256', $canonical, 'cpay-callback-secret', true));

        $response = $this->call(
            'POST',
            '/api/webhooks/cpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CPAY_SIGNATURE_VERSION' => 'callback-v1',
                'HTTP_X_CPAY_SIGNATURE' => $signature,
                'HTTP_X_CPAY_TIMESTAMP' => $timestamp,
                'HTTP_X_CPAY_NONCE' => $nonce,
                'HTTP_X_CPAY_CALLBACK_TASK_ID' => $taskId,
                'HTTP_X_CPAY_MERCHANT_ID' => $merchantId,
                'HTTP_X_CPAY_REFERENCE' => $reference,
            ],
            $rawBody,
        );

        $response->assertOk()->assertJsonPath('data.status', MobileMoneyTransaction::STATUS_SUCCESSFUL);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'internal_reference' => 'opfin-ref-1',
            'provider_reference' => 'cpay-tx-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'webhook_event_id' => 'evt-opfin-1',
        ]);
    }

    public function test_cpay_callback_rejects_invalid_signature(): void
    {
        config()->set('services.cpay.callback_secret', 'cpay-callback-secret');
        config()->set('services.mobile_money.providers.cpay.webhook_secret', 'cpay-callback-secret');

        $rawBody = json_encode([
            'event_id' => 'evt-bad-1',
            'event_type' => 'payment.failed',
            'data' => ['transaction_id' => 'cpay-tx-missing', 'status' => 'FAILED'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/webhooks/cpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CPAY_SIGNATURE_VERSION' => 'callback-v1',
                'HTTP_X_CPAY_SIGNATURE' => 'invalid',
                'HTTP_X_CPAY_TIMESTAMP' => (string) now('UTC')->timestamp,
                'HTTP_X_CPAY_NONCE' => 'bad-nonce',
                'HTTP_X_CPAY_CALLBACK_TASK_ID' => '9',
                'HTTP_X_CPAY_MERCHANT_ID' => '77',
                'HTTP_X_CPAY_REFERENCE' => 'opfin-ref-missing',
            ],
            $rawBody,
        );

        $response->assertUnauthorized();
    }

    public function test_cpay_callback_rejects_terminal_status_regression(): void
    {
        config()->set('services.cpay.callback_secret', 'cpay-callback-secret');
        config()->set('services.cpay.merchant_id', '77');
        config()->set('services.mobile_money.providers.cpay.webhook_secret', 'cpay-callback-secret');

        MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000002',
            'idempotency_key' => 'cpay-terminal-1',
            'internal_reference' => 'opfin-terminal-1',
            'provider_reference' => 'cpay-terminal-tx-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        $payload = [
            'event_id' => 'evt-terminal-regression',
            'event_type' => 'payment.failed',
            'data' => [
                'transaction_id' => 'cpay-terminal-tx-1',
                'merchant_reference' => 'opfin-terminal-1',
                'status' => 'FAILED',
            ],
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now('UTC')->timestamp;
        $canonical = implode("\n", [
            '43',
            '77',
            'opfin-terminal-1',
            $timestamp,
            'callback-nonce-2',
            $rawBody,
        ]);
        $signature = base64_encode(hash_hmac('sha256', $canonical, 'cpay-callback-secret', true));

        $response = $this->call(
            'POST',
            '/api/webhooks/cpay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_CPAY_SIGNATURE_VERSION' => 'callback-v1',
                'HTTP_X_CPAY_SIGNATURE' => $signature,
                'HTTP_X_CPAY_TIMESTAMP' => $timestamp,
                'HTTP_X_CPAY_NONCE' => 'callback-nonce-2',
                'HTTP_X_CPAY_CALLBACK_TASK_ID' => '43',
                'HTTP_X_CPAY_MERCHANT_ID' => '77',
                'HTTP_X_CPAY_REFERENCE' => 'opfin-terminal-1',
            ],
            $rawBody,
        );

        $response->assertStatus(409);
        $this->assertDatabaseHas('mobile_money_transactions', [
            'internal_reference' => 'opfin-terminal-1',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
        ]);
    }
}
