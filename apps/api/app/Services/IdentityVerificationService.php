<?php

namespace App\Services;

use App\Models\CustomerPhoneNumber;
use App\Models\KycCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IdentityVerificationService
{
    public function __construct(
        private readonly CitoCapabilityClient $cito,
        private readonly ServiceEconomicsService $economics,
    ) {}

    public function verify(KycCase $case): KycCase
    {
        $case->loadMissing('user');
        $route = $this->externalRoute();

        if ($route === 'cito' && ! $this->cito->configured()) {
            return $this->markPending($case, [
                'CITO_IDENTITY_NOT_CONFIGURED',
            ]);
        }

        if ($this->shouldUseCito($route)) {
            return $this->verifyViaCito($case);
        }

        if ($route === 'direct' || $this->directFallbackAllowed()) {
            return $this->verifyDirect($case);
        }

        return $this->markPending($case, [
            'IDENTITY_PROVIDER_NOT_CONFIGURED',
            'DIRECT_PROVIDER_FALLBACK_DISABLED',
        ]);
    }

    private function verifyViaCito(KycCase $case): KycCase
    {
        $user = $case->user;
        $ninRequestReference = "kyc:{$case->id}:cito:NIN";

        try {
            $nin = $this->cito->identityCheck($user, $case, 'NIN');
            $ninStatus = strtoupper((string) ($nin['status'] ?? 'ERROR'));
            $ninProviderReference = $this->nullableString($nin['providerReference'] ?? null);
            $this->recordEconomics(
                case: $case,
                capability: 'NIN',
                provider: 'cito',
                route: 'CITO_MANAGED',
                requestReference: $ninRequestReference,
                providerReference: $ninProviderReference,
                status: $ninStatus,
                metadata: [
                    'cito_reference' => $nin['reference'] ?? null,
                    'reason_codes' => array_values(array_filter((array) ($nin['reasonCodes'] ?? []), 'is_string')),
                ],
            );

            if ($ninStatus !== 'PASS') {
                return $this->updateCitoEvidence(
                    case: $case,
                    nin: $nin,
                    phone: null,
                    livenessStatus: 'pending_review',
                    faceStatus: 'pending_review',
                    phoneStatus: 'not_checked',
                    verified: false,
                    extraRiskFlags: [$ninStatus === 'PENDING' ? 'NIN_VALIDATION_PENDING' : 'NIN_VALIDATION_FAILED'],
                );
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->recordEconomics(
                case: $case,
                capability: 'NIN',
                provider: 'cito',
                route: 'CITO_MANAGED',
                requestReference: $ninRequestReference,
                providerReference: null,
                status: 'ERROR',
                metadata: ['automatic_direct_retry_suppressed' => true],
            );

            return $this->markPending($case, [
                'CITO_IDENTITY_PROVIDER_ERROR',
                'DIRECT_RETRY_REQUIRES_EXPLICIT_ROUTE_SWITCH',
            ], provider: 'cito');
        }

        $phoneRequestReference = "kyc:{$case->id}:cito:PHONE_OWNERSHIP";
        try {
            $phone = $this->cito->identityCheck($user, $case, 'PHONE_OWNERSHIP');
            $phoneStatus = strtoupper((string) ($phone['status'] ?? 'ERROR'));
            $phoneProviderReference = $this->nullableString($phone['providerReference'] ?? null);
            $this->recordEconomics(
                case: $case,
                capability: 'PHONE_OWNERSHIP',
                provider: 'cito',
                route: 'CITO_MANAGED',
                requestReference: $phoneRequestReference,
                providerReference: $phoneProviderReference,
                status: $phoneStatus,
                metadata: [
                    'cito_reference' => $phone['reference'] ?? null,
                    'reason_codes' => array_values(array_filter((array) ($phone['reasonCodes'] ?? []), 'is_string')),
                ],
            );

            if ($phoneStatus !== 'PASS') {
                return $this->updateCitoEvidence(
                    case: $case,
                    nin: $nin,
                    phone: $phone,
                    livenessStatus: 'pending_review',
                    faceStatus: 'pending_review',
                    phoneStatus: $phoneStatus === 'PENDING' ? 'pending_review' : 'failed',
                    verified: false,
                    extraRiskFlags: [$phoneStatus === 'PENDING' ? 'NIN_PHONE_LINK_PENDING' : 'NIN_PHONE_LINK_FAILED'],
                );
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->recordEconomics(
                case: $case,
                capability: 'PHONE_OWNERSHIP',
                provider: 'cito',
                route: 'CITO_MANAGED',
                requestReference: $phoneRequestReference,
                providerReference: null,
                status: 'ERROR',
                metadata: ['automatic_direct_retry_suppressed' => true],
            );

            return $this->updateCitoEvidence(
                case: $case,
                nin: $nin,
                phone: null,
                livenessStatus: 'pending_review',
                faceStatus: 'pending_review',
                phoneStatus: 'provider_error',
                verified: false,
                extraRiskFlags: [
                    'CITO_PHONE_OWNERSHIP_PROVIDER_ERROR',
                    'DIRECT_RETRY_REQUIRES_EXPLICIT_ROUTE_SWITCH',
                ],
            );
        }

        if (! $this->directBiometricProviderConfigured()) {
            return $this->updateCitoEvidence(
                case: $case,
                nin: $nin,
                phone: $phone,
                livenessStatus: 'pending_review',
                faceStatus: 'pending_review',
                phoneStatus: 'valid',
                verified: false,
                extraRiskFlags: ['BIOMETRIC_PROVIDER_NOT_CONFIGURED'],
            );
        }

        $biometricReference = "kyc:{$case->id}:direct:BIOMETRIC";
        try {
            $biometric = $this->executeDirectProvider($case);
            $livenessValid = (bool) ($biometric['liveness_valid'] ?? false);
            $faceMatchValid = (bool) ($biometric['face_match_valid'] ?? false);
            $biometricProviderReference = $this->nullableString($biometric['reference'] ?? null);

            $this->recordEconomics(
                case: $case,
                capability: 'BIOMETRIC_LIVENESS_FACE_MATCH',
                provider: 'configured_identity_provider',
                route: 'DIRECT_PROVIDER',
                requestReference: $biometricReference,
                providerReference: $biometricProviderReference,
                status: $livenessValid && $faceMatchValid ? 'PASS' : 'FAIL',
                metadata: [
                    'cito_identity_primary' => true,
                    'direct_provider_used_only_for_unavailable_biometric_capability' => true,
                ],
            );

            return $this->updateCitoEvidence(
                case: $case,
                nin: $nin,
                phone: $phone,
                livenessStatus: $livenessValid ? 'valid' : 'failed',
                faceStatus: $faceMatchValid ? 'valid' : 'failed',
                phoneStatus: 'valid',
                verified: $livenessValid && $faceMatchValid,
                extraRiskFlags: array_values(array_filter([
                    $livenessValid ? null : 'LIVENESS_CHECK_FAILED',
                    $faceMatchValid ? null : 'FACE_MATCH_FAILED',
                ])),
                biometricProviderReference: $biometricProviderReference,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->recordEconomics(
                case: $case,
                capability: 'BIOMETRIC_LIVENESS_FACE_MATCH',
                provider: 'configured_identity_provider',
                route: 'DIRECT_PROVIDER',
                requestReference: $biometricReference,
                providerReference: null,
                status: 'ERROR',
                metadata: ['cito_identity_primary' => true],
            );

            return $this->updateCitoEvidence(
                case: $case,
                nin: $nin,
                phone: $phone,
                livenessStatus: 'provider_error',
                faceStatus: 'provider_error',
                phoneStatus: 'valid',
                verified: false,
                extraRiskFlags: ['BIOMETRIC_PROVIDER_ERROR'],
            );
        }
    }

    private function verifyDirect(KycCase $case): KycCase
    {
        if (! $this->directBiometricProviderConfigured()) {
            return $this->markPending($case, ['IDENTITY_PROVIDER_NOT_CONFIGURED']);
        }

        $requestReference = "kyc:{$case->id}:direct:IDENTITY_BUNDLE";
        try {
            $payload = $this->executeDirectProvider($case);
            $ninValid = (bool) ($payload['nin_valid'] ?? false);
            $livenessValid = (bool) ($payload['liveness_valid'] ?? false);
            $faceMatchValid = (bool) ($payload['face_match_valid'] ?? false);
            $phoneLinkValid = (bool) ($payload['nin_phone_link_valid'] ?? false);
            $verified = $ninValid && $livenessValid && $faceMatchValid && $phoneLinkValid;
            $providerReference = $this->nullableString($payload['reference'] ?? null);

            $this->recordEconomics(
                case: $case,
                capability: 'IDENTITY_BIOMETRIC_BUNDLE',
                provider: 'configured_identity_provider',
                route: 'DIRECT_PROVIDER',
                requestReference: $requestReference,
                providerReference: $providerReference,
                status: $verified ? 'PASS' : 'FAIL',
            );

            $case->update([
                'provider' => 'configured_identity_provider',
                'provider_reference' => $providerReference,
                'liveness_status' => $livenessValid ? 'valid' : 'failed',
                'face_match_status' => $faceMatchValid ? 'valid' : 'failed',
                'nin_phone_link_status' => $phoneLinkValid ? 'valid' : 'failed',
                'status' => $verified ? KycCase::STATUS_VERIFIED : KycCase::STATUS_PENDING_REVIEW,
                'evidence' => array_merge($case->evidence ?? [], [
                    'identity_route' => 'DIRECT_PROVIDER',
                    'provider_reference' => $providerReference,
                ]),
                'risk_flags' => array_values(array_filter([
                    $ninValid ? null : 'NIN_VALIDATION_FAILED',
                    $livenessValid ? null : 'LIVENESS_CHECK_FAILED',
                    $faceMatchValid ? null : 'FACE_MATCH_FAILED',
                    $phoneLinkValid ? null : 'NIN_PHONE_LINK_FAILED',
                ])),
                'reviewed_at' => $verified ? now() : null,
                'expires_at' => $verified ? now()->addYear() : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordEconomics(
                case: $case,
                capability: 'IDENTITY_BIOMETRIC_BUNDLE',
                provider: 'configured_identity_provider',
                route: 'DIRECT_PROVIDER',
                requestReference: $requestReference,
                providerReference: null,
                status: 'ERROR',
            );

            return $this->markPending($case, ['IDENTITY_PROVIDER_ERROR'], provider: 'configured_identity_provider', providerError: true);
        }

        return $case->fresh();
    }

    private function executeDirectProvider(KycCase $case): array
    {
        $url = trim((string) config('services.identity_verification.url'));
        if ($url === '') {
            throw new \InvalidArgumentException('Direct identity provider is not configured.');
        }

        $token = trim((string) config('services.identity_verification.token'));
        $disk = (string) config('services.identity_verification.disk', 'local');
        $front = Storage::disk($disk)->get($case->national_id_front_path);
        $back = Storage::disk($disk)->get($case->national_id_back_path);
        $selfie = Storage::disk($disk)->get($case->selfie_with_id_path);
        $phones = CustomerPhoneNumber::query()
            ->where('user_id', $case->user_id)
            ->whereNotNull('verified_at')
            ->pluck('phone')
            ->values()
            ->all();

        $request = Http::acceptJson()->timeout(15)
            ->attach('national_id_front', $front, 'national-id-front.jpg')
            ->attach('national_id_back', $back, 'national-id-back.jpg')
            ->attach('selfie_with_id', $selfie, 'selfie-with-id.jpg');

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($url, [
            'nin' => $case->national_id,
            'phones' => json_encode($phones, JSON_THROW_ON_ERROR),
        ]);
        $response->throw();

        return (array) $response->json();
    }

    private function updateCitoEvidence(
        KycCase $case,
        array $nin,
        ?array $phone,
        string $livenessStatus,
        string $faceStatus,
        string $phoneStatus,
        bool $verified,
        array $extraRiskFlags = [],
        ?string $biometricProviderReference = null,
    ): KycCase {
        $ninProviderReference = $this->nullableString($nin['providerReference'] ?? null);

        $case->update([
            'provider' => 'cito',
            'provider_reference' => $ninProviderReference,
            'liveness_status' => $livenessStatus,
            'face_match_status' => $faceStatus,
            'nin_phone_link_status' => $phoneStatus,
            'status' => $verified ? KycCase::STATUS_VERIFIED : KycCase::STATUS_PENDING_REVIEW,
            'evidence' => array_merge($case->evidence ?? [], [
                'identity_route' => 'CITO_MANAGED',
                'cito_nin_reference' => $nin['reference'] ?? null,
                'nin_provider_reference' => $ninProviderReference,
                'nin_reason_codes' => array_values(array_filter((array) ($nin['reasonCodes'] ?? []), 'is_string')),
                'cito_phone_reference' => $phone['reference'] ?? null,
                'phone_provider_reference' => $this->nullableString($phone['providerReference'] ?? null),
                'phone_reason_codes' => array_values(array_filter((array) ($phone['reasonCodes'] ?? []), 'is_string')),
                'biometric_route' => $biometricProviderReference ? 'DIRECT_PROVIDER' : 'PENDING_OR_NOT_CONFIGURED',
                'biometric_provider_reference' => $biometricProviderReference,
            ]),
            'risk_flags' => array_values(array_unique(array_filter([
                ...($case->risk_flags ?? []),
                ...$extraRiskFlags,
            ]))),
            'reviewed_at' => $verified ? now() : null,
            'expires_at' => $verified ? now()->addYear() : null,
        ]);

        return $case->fresh();
    }

    private function markPending(
        KycCase $case,
        array $riskFlags,
        string $provider = 'pending',
        bool $providerError = false,
    ): KycCase {
        $case->update([
            'provider' => $provider,
            'liveness_status' => $providerError ? 'provider_error' : 'pending_review',
            'face_match_status' => $providerError ? 'provider_error' : 'pending_review',
            'nin_phone_link_status' => $providerError ? 'provider_error' : 'pending_review',
            'status' => KycCase::STATUS_PENDING_REVIEW,
            'risk_flags' => array_values(array_unique([
                ...($case->risk_flags ?? []),
                ...$riskFlags,
            ])),
        ]);

        return $case->fresh();
    }

    private function recordEconomics(
        KycCase $case,
        string $capability,
        string $provider,
        string $route,
        string $requestReference,
        ?string $providerReference,
        string $status,
        array $metadata = [],
    ): void {
        $this->economics->record([
            'user_id' => $case->user_id,
            'service_code' => 'identity',
            'capability_code' => $capability,
            'provider' => $provider,
            'route' => $route,
            'environment' => $route === 'CITO_MANAGED'
                ? strtoupper((string) config('services.cito.environment', 'SANDBOX'))
                : (app()->environment('production') ? 'PRODUCTION' : 'SANDBOX'),
            'request_reference' => $requestReference,
            'provider_reference' => $providerReference,
            'status' => $status,
            'currency' => 'UGX',
            'metadata' => array_merge([
                'kyc_case_id' => $case->id,
                'purpose' => 'identity_verification',
            ], $metadata),
        ]);
    }

    private function externalRoute(): string
    {
        $route = strtolower(trim((string) config('opfin.integrations.external_service_route', 'auto')));

        return in_array($route, ['auto', 'cito', 'direct'], true) ? $route : 'auto';
    }

    private function shouldUseCito(string $route): bool
    {
        return $route !== 'direct' && $this->cito->configured();
    }

    private function directFallbackAllowed(): bool
    {
        return (bool) config('opfin.integrations.direct_provider_fallback_enabled', true);
    }

    private function directBiometricProviderConfigured(): bool
    {
        return trim((string) config('services.identity_verification.url')) !== '';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
