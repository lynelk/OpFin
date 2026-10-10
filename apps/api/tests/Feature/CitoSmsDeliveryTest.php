<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CitoSmsDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_cito_sms_is_submitted_not_falsely_confirmed_delivered(): void
    {
        $key = openssl_pkey_new(['private_key_bits'=>2048, 'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config([
            'app.env' => 'testing',
            'services.sms_gateway' => 'CITO',
            'services.cito.base_url' => 'https://cito.example.test',
            'services.cito.merchant_number' => 'OPFIN-1',
            'services.cito.private_key' => $pem,
            'services.cito.environment' => 'SANDBOX',
            'services.cito.feature_flags.sms' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://cito.example.test/*' => Http::response([
            'reference' => 'SMS-123', 'status' => 'ACCEPTED',
        ], 202)]);

        $message = SmsMessage::create([
            'to' => '256700000001', 'message' => 'Your message', 'status' => 'Pending',
        ]);
        $job = new SendSms($message);
        $job->handle(app(SmsService::class));
        $this->assertDatabaseHas('sms_messages', [
            'id' => $message->id, 'status' => 'Submitted',
        ]);
        Http::assertSent(fn ($r) => $r->hasHeader('X-CPay-Idempotency-Key', 'sms-message:'.$message->id));
        $job->handle(app(SmsService::class));
        Http::assertSentCount(1);
    }
}
