<?php

namespace App\Services\Cito;

use InvalidArgumentException;
use Illuminate\Support\Str;
use RuntimeException;

class CitoCommunicationsClient
{
    public function createOtpChallenge(string $phone, string $purpose = 'LOGIN', string $locale = 'en-UG', ?string $idempotencyKey = null, string $channel = 'SMS'): array
    {
        app(CitoFeatureGate::class)->requireEnabled('otp');
        if (! preg_match('/^\\+[1-9][0-9]{7,14}$/', $phone)) {
            throw new InvalidArgumentException('OTP recipient must be an international phone number.');
        }
        $channel = strtoupper(trim($channel));
        if (! in_array($channel, ['SMS', 'WHATSAPP'], true)) {
            throw new InvalidArgumentException('Cito OTP delivery channel is unsupported.');
        }
        $body = [
            'merchantNumber' => $this->merchant(), 'recipient' => $phone,
            'purpose' => $purpose, 'locale' => $locale,
            'channels' => [$channel],
        ];
        if ($channel === 'WHATSAPP') {
            app(CitoFeatureGate::class)->requireEnabled('otp_whatsapp');
            $template = trim((string) config('services.cito.whatsapp_otp_template', ''));
            if ($template === '') {
                throw new InvalidArgumentException('Approved WhatsApp OTP template is required.');
            }
            $body['template'] = $template;
        }
        return $this->post('/api/v2/communication/otp/challenges', $body, $idempotencyKey ?? (string) Str::uuid());
    }

    public function verifyOtpChallenge(string $challengeId, string $code): array
    {
        app(CitoFeatureGate::class)->requireEnabled('otp');
        if (! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $challengeId)) {
            throw new InvalidArgumentException('Invalid OTP challenge reference.');
        }
        if (! preg_match('/^[0-9A-Za-z]{4,10}$/', $code)) {
            throw new InvalidArgumentException('Invalid OTP code format.');
        }
        return $this->post('/api/v2/communication/otp/challenges/'.$challengeId.'/verify', [
            'merchantNumber' => $this->merchant(), 'code' => $code,
        ]);
    }

    public function sendSms(string $recipient, string $content, string $purpose = 'NOTIFICATION', ?string $idempotencyKey = null): array
    {
        app(CitoFeatureGate::class)->requireEnabled('sms');
        if (! preg_match('/^\\+[1-9][0-9]{7,14}$/', $recipient) || trim($content) === '') {
            throw new InvalidArgumentException('Invalid SMS recipient or empty message.');
        }
        return $this->post('/api/v2/communication/messages', [
            'merchantNumber' => $this->merchant(), 'recipient' => $recipient,
            'content' => $content, 'purpose' => $purpose, 'currencyCode' => 'UGX',
            'requireDeliveryReceipts' => true,
        ], $idempotencyKey);
    }

    public function messageStatus(string $reference): array
    {
        app(CitoFeatureGate::class)->requireEnabled('sms');
        if (! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $reference)) {
            throw new InvalidArgumentException('Invalid message reference.');
        }
        return $this->decode(app(SignedCitoTransport::class)->request(
            'GET', '/api/v2/communication/messages/'.$reference, [], ['merchantNumber' => $this->merchant()]
        ));
    }

    private function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        return $this->decode(app(SignedCitoTransport::class)->request('POST', $path, $body, [], $idempotencyKey));
    }

    private function decode(\Illuminate\Http\Client\Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('Cito communications request failed (HTTP '.$response->status().').');
        }
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Invalid Cito communications response.');
        }
        // Do not log or return an OTP value from create requests; Cito must never supply it.
        return $json;
    }

    private function merchant(): string
    {
        return trim((string) config('services.cito.merchant_number'));
    }
}
