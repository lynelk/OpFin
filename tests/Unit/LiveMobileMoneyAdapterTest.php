<?php

namespace Tests\Unit;

use App\Models\MobileMoneyTransaction;
use App\Services\MobileMoney\Adapters\AirtelMoneyAdapter;
use App\Services\MobileMoney\Adapters\MtnMobileMoneyAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class LiveMobileMoneyAdapterTest extends TestCase
{
    public function test_mtn_adapter_requires_live_configuration(): void
    {
        Config::set('services.mobile_money.providers.mtn.base_url', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MTN Mobile Money base_url is not configured.');

        (new MtnMobileMoneyAdapter())->disburse($this->transaction('mtn'));
    }

    public function test_mtn_adapter_posts_disbursement_and_normalizes_pending_response(): void
    {
        Config::set('services.mobile_money.providers.mtn.base_url', 'https://mtn.example.test');
        Config::set('services.mobile_money.providers.mtn.access_token', 'mtn-token');
        Config::set('services.mobile_money.providers.mtn.disbursement_sub_key', 'mtn-sub-key');
        Config::set('services.mobile_money.providers.mtn.target_env', 'sandbox');

        Http::fake([
            'https://mtn.example.test/disbursement/v1_0/transfer' => Http::response([], 202),
        ]);

        $response = (new MtnMobileMoneyAdapter())->disburse($this->transaction('mtn'));

        $this->assertTrue($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_PENDING, $response->status);
        $this->assertSame('internal-ref-1', $response->providerReference);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer mtn-token')
            && $request->hasHeader('X-Reference-Id', 'internal-ref-1'));
    }

    public function test_airtel_adapter_requires_live_configuration(): void
    {
        Config::set('services.mobile_money.providers.airtel.base_url', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Airtel Money base_url is not configured.');

        (new AirtelMoneyAdapter())->collect($this->transaction('airtel'));
    }

    public function test_airtel_adapter_posts_collection_and_normalizes_pending_response(): void
    {
        Config::set('services.mobile_money.providers.airtel.base_url', 'https://airtel.example.test');
        Config::set('services.mobile_money.providers.airtel.client_id', 'airtel-client');
        Config::set('services.mobile_money.providers.airtel.access_token', 'airtel-token');
        Config::set('services.mobile_money.providers.airtel.country', 'UG');
        Config::set('services.mobile_money.providers.airtel.currency', 'UGX');

        Http::fake([
            'https://airtel.example.test/merchant/v1/payments/' => Http::response([
                'data' => ['transaction' => ['id' => 'airtel-ref-1', 'status_code' => 'TIP']],
            ], 202),
        ]);

        $response = (new AirtelMoneyAdapter())->collect($this->transaction('airtel'));

        $this->assertTrue($response->successful);
        $this->assertSame(MobileMoneyTransaction::STATUS_PENDING, $response->status);
        $this->assertSame('airtel-ref-1', $response->providerReference);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer airtel-token')
            && $request->hasHeader('X-Country', 'UG'));
    }

    private function transaction(string $provider): MobileMoneyTransaction
    {
        return new MobileMoneyTransaction([
            'provider' => $provider,
            'direction' => MobileMoneyTransaction::DIRECTION_DISBURSEMENT,
            'amount_minor' => 100000,
            'currency' => 'UGX',
            'phone' => '256700000001',
            'idempotency_key' => 'idem-1',
            'internal_reference' => 'internal-ref-1',
            'status' => MobileMoneyTransaction::STATUS_PROCESSING,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_UNRECONCILED,
        ]);
    }
}
