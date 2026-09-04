<?php

namespace Tests\Feature;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use App\Services\MobileMoney\MobileMoneyProviderManager;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MobileMoneyAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_disbursement_is_idempotent_and_audited(): void
    {
        config()->set('services.mobile_money.default_provider', 'mock');
        $service = app(MobileMoneyService::class);

        $first = $service->disburse([
            'idempotency_key' => 'disbursement-key-1',
            'amount_minor' => 100000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'description' => 'Test disbursement',
        ]);
        $second = $service->disburse([
            'idempotency_key' => 'disbursement-key-1',
            'amount_minor' => 100000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'description' => 'Test disbursement duplicate',
        ]);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertSame(MobileMoneyTransaction::STATUS_PENDING, $first->status);
        $this->assertNotNull($first->provider_reference);
        $this->assertNotEmpty($first->metadata['instruction_fingerprint'] ?? null);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'mobile_money.disbursement.requested',
            'subject_type' => MobileMoneyTransaction::class,
            'subject_id' => $first->id,
        ]);
    }

    public function test_idempotency_key_cannot_be_reused_for_different_money_movement(): void
    {
        config()->set('services.mobile_money.default_provider', 'mock');
        $service = app(MobileMoneyService::class);

        $service->collect([
            'idempotency_key' => 'bound-key-001',
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'internal_reference' => 'intent-001',
            'purpose' => 'loan_repayment',
            'source_type' => 'loan',
            'source_id' => 1,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different canonical money instruction');

        $service->collect([
            'idempotency_key' => 'bound-key-001',
            'amount_minor' => 50001,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'internal_reference' => 'intent-001',
            'purpose' => 'loan_repayment',
            'source_type' => 'loan',
            'source_id' => 1,
        ]);
    }

    public function test_duplicate_webhook_does_not_create_duplicate_transactions_or_ledger_entries(): void
    {
        config()->set('services.mobile_money.default_provider', 'mock');
        config()->set('services.mobile_money.providers.mock.webhook_secret', 'test-secret');

        $service = app(MobileMoneyService::class);
        $transaction = $service->collect([
            'idempotency_key' => 'collection-key-1',
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000002',
            'description' => 'Test collection',
        ]);

        $payload = [
            'event_id' => 'webhook-event-1',
            'provider_reference' => $transaction->provider_reference,
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'message' => 'Collected',
        ];
        $signature = hash_hmac('sha256', json_encode($payload), 'test-secret');

        $first = $service->processWebhook('mock', $payload, ['X-Opfin-Mobile-Money-Signature' => $signature]);
        $second = $service->processWebhook('mock', $payload, ['X-Opfin-Mobile-Money-Signature' => $signature]);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(MobileMoneyTransaction::STATUS_SUCCESSFUL, $first->fresh()->status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_PENDING, $first->fresh()->reconciliation_status);
    }

    public function test_cpay_refund_and_reversal_are_terminal_reversed_status(): void
    {
        $adapter = app(CpayV2Adapter::class);

        foreach (['REFUNDED', 'REVERSED'] as $status) {
            $response = $adapter->processWebhook([
                'eventId' => 'event-'.strtolower($status),
                'transactionId' => 'cpay-'.strtolower($status),
                'status' => $status,
            ]);
            $this->assertSame(MobileMoneyTransaction::STATUS_REVERSED, $response->status);
            $this->assertSame(MobileMoneyTransaction::RECONCILIATION_PENDING, $response->reconciliationStatus);
            $this->assertFalse($response->retryable);
        }

        $eventOnly = $adapter->processWebhook([
            'eventId' => 'event-refund-completed',
            'eventType' => 'refund.completed',
            'transactionId' => 'cpay-refund-completed',
        ]);
        $this->assertSame(MobileMoneyTransaction::STATUS_REVERSED, $eventOnly->status);
    }

    public function test_uncertified_cpay_reversal_request_does_not_corrupt_successful_state(): void
    {
        $transaction = MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_DISBURSEMENT,
            'amount_minor' => 75000,
            'currency' => 'UGX',
            'phone' => '256700000011',
            'idempotency_key' => 'cpay-success-reversal-guard',
            'internal_reference' => 'CPAY-SUCCESS-REVERSAL-GUARD',
            'provider_reference' => 'provider-success-guard',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
        ]);

        try {
            app(MobileMoneyService::class)->reverse($transaction, 'operator test');
            $this->fail('Uncertified CPay reversal must fail closed.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('not enabled', $exception->getMessage());
        }

        $transaction->refresh();
        $this->assertSame(MobileMoneyTransaction::STATUS_SUCCESSFUL, $transaction->status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_MATCHED, $transaction->reconciliation_status);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.mobile_money.providers.mock.webhook_secret', 'test-secret');
        $this->expectException(InvalidArgumentException::class);
        app(MobileMoneyService::class)->processWebhook('mock', [
            'event_id' => 'bad-webhook',
            'provider_reference' => 'mock-reference',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
        ], ['X-Opfin-Mobile-Money-Signature' => 'bad-signature']);
    }

    public function test_mobile_money_requires_positive_integer_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(MobileMoneyService::class)->collect([
            'idempotency_key' => 'invalid-amount-key',
            'amount_minor' => 0,
            'currency' => 'UGX',
            'phone' => '256700000002',
        ]);
    }

    public function test_mobile_money_requires_valid_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(MobileMoneyService::class)->collect([
            'idempotency_key' => 'invalid-currency-key',
            'amount_minor' => 50000,
            'currency' => 'UGANDA-SHILLINGS',
            'phone' => '256700000002',
        ]);
    }

    public function test_mobile_money_requires_phone_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(MobileMoneyService::class)->disburse([
            'idempotency_key' => 'missing-phone-key',
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '',
        ]);
    }

    public function test_cpay_is_the_only_live_money_movement_adapter(): void
    {
        $this->assertInstanceOf(MobileMoneyProviderInterface::class, app(CpayV2Adapter::class));
        $manager = app(MobileMoneyProviderManager::class);
        foreach (['mtn', 'airtel'] as $provider) {
            try {
                $manager->provider($provider);
                $this->fail("Direct {$provider} provider must not be available.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('Route all money movement through CPay', $exception->getMessage());
            }
        }
    }
}
