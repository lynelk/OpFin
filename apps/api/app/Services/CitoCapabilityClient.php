<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CitoCapabilityClient
{
    public function configured(): bool
    {
        return trim((string) config('services.cito.base_url')) !== ''
            && trim((string) config('services.cito.merchant_number')) !== ''
            && trim((string) config('services.cito.private_key')) !== '';
    }

    public function creditScore(User $user, ConsentRecord $consent, string $capability = 'CREDIT_SCORE_CRB'): array
    {
        if (! $this->configured()) {
            throw new InvalidArgumentException('Cito capability integration is not configured.');
        }

        $payload = [
            'merchantNumber' => $this->merchantNumber(),
            'capability' => strtoupper(trim($capability)),
            'country' => strtoupper((string) config('opfin.default_country', 'UG')),
            'subject' => [
                'nationalId' => $user->national_id,
                'fullName' => trim(implode(' ', array_filter([
                    $user->first_name,
                    $user->other_name,
                    $user->last_name,
                ]))) ?: ($user->name ?: null),
                'phone' => $user->phone,
                'attributes' => [
                    'identifierType' => 'NATIONAL_ID',
                    'identifierValue' => (string) $user->national_id,
                    'customerReference' => 'user:'.$user->id,
                ],
            ],
            'consent' => [
                'obtained' => true,
                'purpose' => $consent->purpose,
                'reference' => 'consent:'.$consent->id,
            ],
            'attributes' => [
                'identifierType' => 'NATIONAL_ID',
                'identifierValue' => (string) $user->national_id,
                'msisdn' => (string) $user->phone,
                'customerReference' => 'user:'.$user->id,
            ],
        ];

        $response = $this->sendSigned('/api/v2/credit/scores', $payload);
        if (! $response->successful()) {
            $code = (string) ($response->json('code') ?? 'CITO_CAPABILITY_ERROR');
            throw new RuntimeException("Cito capability request failed with {$code} (HTTP {$response->status()}).");
        }

        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('Cito capability response was not valid JSON.');
        }

        return $result;
    }

    private function sendSigned(string $path, array $payload): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['POST', $path, '', $timestamp, $nonce, hash('sha256', $body)]);

        return Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CPay-Merchant-Number' => $this->merchantNumber(),
            'X-CPay-Signature-Version' => 'v2',
            'X-CPay-Timestamp' => $timestamp,
            'X-CPay-Nonce' => $nonce,
            'X-CPay-Signature' => $this->sign($canonical),
            'X-CPay-Environment' => strtoupper((string) config('services.cito.environment', 'SANDBOX')),
        ])
            ->timeout((int) config('services.cito.timeout_seconds', 15))
            ->withBody($body, 'application/json')
            ->post(rtrim((string) config('services.cito.base_url'), '/').$path);
    }

    private function sign(string $canonical): string
    {
        $privateKeyValue = str_replace('\\n', "\n", (string) config('services.cito.private_key'));
        $privateKey = openssl_pkey_get_private($privateKeyValue);
        if ($privateKey === false) {
            throw new RuntimeException('Cito private key is invalid or unreadable.');
        }

        if (! openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Cito capability request.');
        }

        return base64_encode($signature);
    }

    private function merchantNumber(): string
    {
        return (string) config('services.cito.merchant_number');
    }
}
