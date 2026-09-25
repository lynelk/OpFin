<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\KycCase;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
        return $this->executeCapability(
            path: '/api/v2/credit/scores',
            user: $user,
            nationalId: (string) $user->national_id,
            capability: $capability,
            purpose: $consent->purpose,
            consentReference: 'consent:'.$consent->id,
        );
    }

    public function identityCheck(User $user, KycCase $case, string $capability, bool $forceRefresh = false): array
    {
        if ((int) $case->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('Identity verification must target the case owner.');
        }
        $capability = strtoupper(trim($capability));
        $path = match ($capability) {
            'NIN' => '/api/v2/identity/nin/verifications',
            'PHONE_OWNERSHIP' => '/api/v2/identity/verifications',
            default => throw new InvalidArgumentException("Unsupported Cito identity capability: {$capability}"),
        };
        if ($capability !== 'NIN') {
            return $this->executeCapability(
                path: $path, user: $user, nationalId: (string) $case->national_id,
                capability: $capability, purpose: 'identity_verification',
                consentReference: 'kyc-case:'.$case->id,
            );
        }

        $evidence = app(VerifiedIdentityEvidenceService::class);
        if (! $forceRefresh && ($cached = $evidence->reusableNin($user, $case)) !== null) {
            return $cached;
        }

        // Serialise NIN checks without locking the customer's financial rows.
        $lock = Cache::lock('opfin:identity:nin:'.$user->id, max(60, (int) config('services.cito.timeout_seconds', 15) + 30));
        if (! $lock->get()) {
            throw new RuntimeException('NIN verification is already in progress. Check its status before retrying.');
        }
        try {
            if (! $forceRefresh && ($cached = $evidence->reusableNin($user, $case)) !== null) {
                return $cached;
            }
            $result = $this->executeCapability(
                path: $path, user: $user, nationalId: (string) $case->national_id,
                capability: $capability, purpose: 'identity_verification',
                consentReference: 'kyc-case:'.$case->id,
            );
            app(IdentityEvidenceRevocationService::class)->recordDefinitiveResult($user, $case, $result);

            return $result;
        } finally {
            $lock->release();
        }
    }

    private function executeCapability(
        string $path,
        User $user,
        string $nationalId,
        string $capability,
        string $purpose,
        string $consentReference,
    ): array {
        if (! $this->configured()) {
            throw new InvalidArgumentException('Cito capability integration is not configured.');
        }
        $fullName = trim(implode(' ', array_filter([
            $user->first_name,
            $user->other_name,
            $user->last_name,
        ]))) ?: ($user->name ?: null);
        $payload = [
            'merchantNumber' => $this->merchantNumber(),
            'capability' => strtoupper(trim($capability)),
            'country' => strtoupper((string) config('opfin.default_country', 'UG')),
            'subject' => [
                'nationalId' => $nationalId,
                'fullName' => $fullName,
                'phone' => $user->phone,
                'attributes' => [
                    'identifierType' => 'NATIONAL_ID',
                    'identifierValue' => $nationalId,
                    'customerReference' => 'user:'.$user->id,
                ],
            ],
            'consent' => [
                'obtained' => true,
                'purpose' => trim($purpose),
                'reference' => trim($consentReference),
            ],
            'attributes' => [
                'identifierType' => 'NATIONAL_ID',
                'identifierValue' => $nationalId,
                'nin' => $nationalId,
                'msisdn' => (string) $user->phone,
                'fullName' => $fullName,
                'customerReference' => 'user:'.$user->id,
            ],
        ];
        $response = $this->sendSigned($path, $payload);
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
            ->send('POST', rtrim((string) config('services.cito.base_url'), '/').$path);
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
