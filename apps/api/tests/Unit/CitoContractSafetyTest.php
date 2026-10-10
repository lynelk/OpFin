<?php

namespace Tests\Unit;

use App\Models\MobileMoneyTransaction;
use App\Services\Cito\CitoBillingClient;
use App\Services\Cito\CitoCommunicationsClient;
use App\Services\MobileMoney\Adapters\CpayV2Adapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CitoContractSafetyTest extends TestCase
{
    private function configureSigning(): void
    {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        Config::set('services.cito.base_url', 'https://cito.example.test');
        Config::set('services.cito.merchant_number', 'OPFIN-1');
        Config::set('services.cito.private_key', $private);
        Config::set('services.cito.environment', 'SANDBOX');
        Config::set('services.cito.feature_flags.otp', true);
        Config::set('services.cito.feature_flags.sms', true);
    }

    public function test_otp_creation_has_contract_required_idempotency_header(): void
    {
        $this->configureSigning();
        Http::fake(['https://cito.example.test/*' => Http::response(['challengeId' => 'OTP-123', 'status' => 'PENDING'], 201)]);
        app(CitoCommunicationsClient::class)->createOtpChallenge('+256700000001');
        Http::assertSent(fn ($r) =>
            $r->hasHeader('X-CPay-Idempotency-Key')
            && strlen((string) ($r->header('X-CPay-Idempotency-Key')[0] ?? '')) > 8
            && $r->data()['merchantNumber'] === 'OPFIN-1'
            && $r->data()['recipient'] === '+256700000001'
        );
    }

    public function test_otp_verification_supplies_merchant_and_code(): void
    {
        $this->configureSigning();
        Http::fake(['https://cito.example.test/*' => Http::response(['status' => 'VERIFIED'], 200)]);
        $response = app(CitoCommunicationsClient::class)->verifyOtpChallenge('OTP-123', '123456');
        $this->assertSame('VERIFIED', $response['status']);
        Http::assertSent(fn ($r) => $r->data()['merchantNumber'] === 'OPFIN-1'
            && $r->data()['code'] === '123456');
    }

    public function test_sms_status_uses_signed_merchant_query(): void
    {
        $this->configureSigning();
        Http::fake(['https://cito.example.test/*' => Http::response(['status' => 'DELIVERED'], 200)]);
        app(CitoCommunicationsClient::class)->messageStatus('MSG-123');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'merchantNumber=OPFIN-1'));
    }

    public function test_whatsapp_otp_requires_separate_entitlement_and_approved_template(): void
    {
        $this->configureSigning();
        Config::set('services.cito.feature_flags.otp_whatsapp', false);
        Http::fake(['https://cito.example.test/*' => Http::response(['status' => 'PENDING'], 201)]);
        try {
            app(CitoCommunicationsClient::class)->createOtpChallenge(
                '+256700000001', 'REGISTRATION', 'en-UG', 'whatsapp-test-key', 'WHATSAPP'
            );
            $this->fail('WhatsApp OTP must be separately entitled.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }

        Config::set('services.cito.feature_flags.otp_whatsapp', true);
        Config::set('services.cito.whatsapp_otp_template', '');
        $this->expectException(InvalidArgumentException::class);
        app(CitoCommunicationsClient::class)->createOtpChallenge(
            '+256700000001', 'REGISTRATION', 'en-UG', 'whatsapp-test-key', 'WHATSAPP'
        );
    }

    public function test_entitled_whatsapp_otp_uses_only_published_otp_contract(): void
    {
        $this->configureSigning();
        Config::set('services.cito.feature_flags.otp_whatsapp', true);
        Config::set('services.cito.whatsapp_otp_template', 'approved_signup_otp');
        Http::fake(['https://cito.example.test/*' => Http::response(['status' => 'PENDING'], 201)]);

        app(CitoCommunicationsClient::class)->createOtpChallenge(
            '+256700000001', 'REGISTRATION', 'en-UG', 'whatsapp-test-key', 'WHATSAPP'
        );
        Http::assertSent(fn ($r) => $r->data()['channels'] === ['WHATSAPP']
            && $r->data()['template'] === 'approved_signup_otp'
            && str_contains($r->url(), '/communication/otp/challenges')
            && $r->hasHeader('X-CPay-Idempotency-Key', 'whatsapp-test-key'));
    }

    public function test_billing_write_fails_closed_even_with_service_key(): void
    {
        Config::set('services.cito.base_url', 'https://cito.example.test');
        Config::set('services.cito.baas_api_key', 'fake-private-test-key');
        Config::set('services.cito.baas_write_enabled', false);
        $this->expectException(RuntimeException::class);
        app(CitoBillingClient::class)->chargeCommit('charge-123');
    }

    public function test_billing_quote_rejects_imprecise_amount_format(): void
    {
        Config::set('services.cito.base_url', 'https://cito.example.test');
        Config::set('services.cito.baas_api_key', 'fake-private-test-key');
        $this->expectException(InvalidArgumentException::class);
        app(CitoBillingClient::class)->quote([
            'billingAccountReference'=>'acct1', 'serviceCode'=>'SMS', 'meterCode'=>'API',
            'ratingBaseAmount'=>'not-a-number', 'sourceCurrency'=>'UGX',
        ]);
    }

    public function test_cpay_acceptance_cannot_force_successful_finality(): void
    {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        Config::set('services.cpay.base_url', 'https://cito.example.test');
        Config::set('services.cpay.private_key', $private);
        Config::set('services.cpay.merchant_number', 'OPFIN-1');
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/webhook');
        Http::fake(['https://cito.example.test/*' => Http::response(['status'=>'SUCCESSFUL','reference'=>'ref-1'],202)]);
        $tx = new MobileMoneyTransaction();
        $tx->direction = 'collection';
        $tx->amount_minor = 5000;
        $tx->currency = 'UGX';
        $tx->phone = '256700000001';
        $tx->internal_reference = 'ref-1';
        $tx->idempotency_key = 'idem-1';
        $tx->metadata = [];
        $response = app(CpayV2Adapter::class)->collect($tx);
        $this->assertSame(MobileMoneyTransaction::STATUS_PENDING, $response->status);
    }

    public function test_cpay_5xx_is_unknown_and_must_not_become_terminal_failed(): void
    {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        Config::set('services.cpay.base_url', 'https://cito.example.test');
        Config::set('services.cpay.private_key', $private);
        Config::set('services.cpay.merchant_number', 'OPFIN-1');
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/webhook');
        Http::fake(['https://cito.example.test/*' => Http::response(['status'=>'FAILED'],503)]);
        $tx = new MobileMoneyTransaction();
        $tx->direction = 'collection';
        $tx->amount_minor = 5000;
        $tx->currency = 'UGX';
        $tx->phone = '256700000001';
        $tx->internal_reference = 'ref-1';
        $tx->idempotency_key = 'idem-1';
        $tx->metadata = [];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outcome unknown');
        app(CpayV2Adapter::class)->collect($tx);
    }
}
