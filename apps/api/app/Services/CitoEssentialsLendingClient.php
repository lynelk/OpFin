<?php

namespace App\Services;

use App\Models\CreditProfile;
use App\Models\EssentialsAdvance;
use App\Models\EssentialsQuote;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CitoEssentialsLendingClient
{
    public function configured(): bool
    {
        return trim((string) config('services.cito.base_url')) !== ''
            && trim((string) config('services.cito.merchant_number')) !== ''
            && trim((string) config('services.cito.private_key')) !== ''
            && trim((string) config('services.cito.essentials_lending_path')) !== '';
    }

    public function requestCreditLine(
        User $user,
        CreditProfile $profile,
        object $partner,
        object $product,
        ?int $financialSpaceId,
        string $consentReference,
    ): array {
        if (! $this->configured()) {
            throw new RuntimeException('Cito Essentials lending orchestration is not configured.');
        }

        $path = trim((string) config('services.cito.essentials_lending_path'));
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        $payload = [
            'merchantNumber' => (string) config('services.cito.merchant_number'),
            'capability' => 'ESSENTIALS_CREDIT_LINE',
            'country' => strtoupper((string) config('opfin.default_country', 'UG')),
            'customerReference' => 'user:'.$user->id,
            'financialSpaceId' => $financialSpaceId,
            'lender' => [
                'partnerCode' => $partner->code,
                'partnerProductCode' => $product->code,
            ],
            'creditProfile' => [
                'compositeScore' => $profile->composite_score,
                'band' => $profile->band,
                'coveragePercent' => $profile->coverage_percent,
                'availableToBorrowMinor' => $profile->available_to_borrow_minor,
                'modelVersion' => $profile->model_version,
                'scoredAt' => $profile->scored_at?->toISOString(),
            ],
            'consent' => [
                'obtained' => true,
                'purpose' => 'credit_processing',
                'reference' => $consentReference,
            ],
        ];

        return $this->sendSigned($path, $payload);
    }

    public function drawdownConfigured(): bool
    {
        return trim((string) config('services.cito.base_url')) !== ''
            && trim((string) config('services.cito.merchant_number')) !== ''
            && trim((string) config('services.cito.private_key')) !== ''
            && trim((string) config('services.cito.essentials_drawdown_path')) !== ''
            && trim((string) config('services.cito.essentials_drawdown_status_path')) !== ''
            && trim((string) config('services.cito.essentials_drawdown_release_path')) !== '';
    }

    public function authoriseDrawdown(
        User $user,
        EssentialsAdvance $advance,
        EssentialsQuote $quote,
        object $partner,
        object $product,
        string $consentReference,
    ): array {
        if (! $this->drawdownConfigured()) {
            throw new RuntimeException('Cito Essentials lender drawdown is not configured.');
        }

        $path = trim((string) config('services.cito.essentials_drawdown_path'));
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->sendSigned($path, [
            'merchantNumber' => (string) config('services.cito.merchant_number'),
            'capability' => 'ESSENTIALS_CREDIT_DRAWDOWN',
            'country' => strtoupper((string) config('opfin.default_country', 'UG')),
            'currency' => $advance->currency,
            'customerReference' => 'user:'.$user->id,
            'financialSpaceId' => $advance->financial_space_id,
            'requestReference' => $advance->reference,
            'amountMinor' => $advance->principal_minor,
            'purposeCategory' => $quote->purpose_category,
            'lender' => [
                'partnerCode' => $partner->code,
                'partnerProductCode' => $product->code,
                'creditLineReference' => $advance->lender_contract_reference,
            ],
            'consent' => [
                'obtained' => true,
                'purpose' => 'credit_processing',
                'reference' => $consentReference,
            ],
        ]);
    }

    public function drawdownStatus(string $reference, string $referenceType = 'provider'): array
    {
        if (! $this->drawdownConfigured()) {
            throw new RuntimeException('Cito Essentials lender drawdown status is not configured.');
        }

        $path = trim((string) config('services.cito.essentials_drawdown_status_path'));
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->sendSigned($path, [
            'merchantNumber' => (string) config('services.cito.merchant_number'),
            'capability' => 'ESSENTIALS_CREDIT_DRAWDOWN_STATUS',
            'reference' => $reference,
            'referenceType' => $referenceType === 'internal' ? 'REQUEST_REFERENCE' : 'PROVIDER_REFERENCE',
        ]);
    }

    public function releaseDrawdown(EssentialsAdvance $advance, string $reason): array
    {
        if (! $this->drawdownConfigured()) {
            throw new RuntimeException('Cito Essentials lender drawdown release is not configured.');
        }
        if (! $advance->lender_funding_reference) {
            throw new RuntimeException('A lender funding reference is required before drawdown release.');
        }

        $path = trim((string) config('services.cito.essentials_drawdown_release_path'));
        $path = str_starts_with($path, '/') ? $path : '/'.$path;

        return $this->sendSigned($path, [
            'merchantNumber' => (string) config('services.cito.merchant_number'),
            'capability' => 'ESSENTIALS_CREDIT_DRAWDOWN_RELEASE',
            'reference' => $advance->lender_funding_reference,
            'requestReference' => $advance->reference.':release',
            'reason' => $reason,
        ]);
    }

    private function sendSigned(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $nonce = (string) Str::uuid();
        $canonical = implode("\n", ['POST', $path, '', $timestamp, $nonce, hash('sha256', $body)]);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-CPay-Merchant-Number' => (string) config('services.cito.merchant_number'),
            'X-CPay-Signature-Version' => 'v2',
            'X-CPay-Timestamp' => $timestamp,
            'X-CPay-Nonce' => $nonce,
            'X-CPay-Signature' => $this->sign($canonical),
            'X-CPay-Environment' => strtoupper((string) config('services.cito.environment', 'sandbox')),
        ])
            ->timeout((int) config('services.cito.timeout_seconds', 15))
            ->withBody($body, 'application/json')
            ->send('POST', rtrim((string) config('services.cito.base_url'), '/').$path);

        return $this->decode($response);
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            $code = (string) ($response->json('code') ?? 'CITO_ESSENTIALS_LENDING_ERROR');
            throw new RuntimeException("Cito Essentials lending request failed with {$code} (HTTP {$response->status()}).");
        }
        $result = $response->json();
        if (! is_array($result)) {
            throw new RuntimeException('Cito Essentials lending response was not valid JSON.');
        }

        return $result;
    }

    private function sign(string $canonical): string
    {
        $privateKeyValue = str_replace('\\n', "\n", (string) config('services.cito.private_key'));
        $privateKey = openssl_pkey_get_private($privateKeyValue);
        if ($privateKey === false) {
            throw new RuntimeException('Cito private key is invalid or unreadable.');
        }
        if (! openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Cito Essentials lending request.');
        }

        return base64_encode($signature);
    }
}
