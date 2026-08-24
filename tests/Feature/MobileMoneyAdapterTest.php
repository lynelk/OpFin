<?php

namespace Tests\Feature;

use App\Contracts\MobileMoneyProviderInterface;
use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\Adapters\AirtelMoneyAdapter;
use App\Services\MobileMoney\Adapters\MtnMobileMoneyAdapter;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'mobile_money.disbursement.requested',
            'subject_type' => MobileMoneyTransaction::class,
            'subject_id' => $first->id,
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

        $first = $service->processWebhook('mock', $payload, [
            'X-Opfin-Mobile-Money-Signature' => $signature,
        ]);
        $second = $service->processWebhook('mock', $payload, [
            'X-Opfin-Mobile-Money-Signature' => $signature,
        ]);

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('mobile_money_transactions', 1);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame(MobileMoneyTransaction::STATUS_SUCCESSFUL, $first->fresh()->status);
        $this->assertSame(MobileMoneyTransaction::RECONCILIATION_PENDING, $first->fresh()->reconciliation_status);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config()->set('services.mobile_money.providers.mock.webhook_secret', 'test-secret');

        $this->expectException(\InvalidArgumentException::class);

        app(MobileMoneyService::class)->processWebhook('mock', [
            'event_id' => 'bad-webhook',
            'provider_reference' => 'mock-reference',
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
        ], [
            'X-Opfin-Mobile-Money-Signature' => 'bad-signature',
        ]);
    }

    public function test_mobile_money_requires_positive_integer_minor_units(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(MobileMoneyService::class)->collect([
            'idempotency_key' => 'invalid-amount-key',
            'amount_minor' => 0,
            'currency' => 'UGX',
            'phone' => '256700000002',
        ]);
    }

    public function test_mobile_money_requires_phone_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(MobileMoneyService::class)->disburse([
            'idempotency_key' => 'missing-phone-key',
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '',
        ]);
    }

    public function test_mtn_and_airtel_adapters_implement_the_live_provider_contract(): void
    {
        $this->assertInstanceOf(MobileMoneyProviderInterface::class, new MtnMobileMoneyAdapter);
        $this->assertInstanceOf(MobileMoneyProviderInterface::class, new AirtelMoneyAdapter);
    }
}
