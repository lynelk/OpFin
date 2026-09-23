<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\CrbReport;
use App\Models\CreditScore;
use App\Models\CreditScoreComponent;
use App\Models\CustomerPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ExternalScoringService
{
    public function __construct(
        private readonly CitoCapabilityClient $cito,
        private readonly ServiceEconomicsService $economics,
    ) {}

    public function refresh(User $user): array
    {
        return [
            'crb' => $this->refreshCrb($user),
            'mno' => $this->refreshConfiguredSource('mno', $user),
            'third_party' => $this->refreshConfiguredSource('third_party', $user),
        ];
    }

    private function refreshCrb(User $user): CreditScoreComponent
    {
        $weight = (float) config('opfin.credit.scoring_weights.crb', 40);
        $fresh = CrbReport::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('received_at')
            ->latest('received_at')
            ->first();

        if ($fresh) {
            if ($fresh->status !== CrbReport::STATUS_ADVERSE && $fresh->score === null) {
                return $this->write(
                    $user,
                    'crb',
                    $weight,
                    null,
                    CreditScoreComponent::STATUS_ERROR,
                    $fresh->provider_reference,
                    ['CRB_SCORE_MISSING'],
                    ['status' => $fresh->status, 'provider' => $fresh->provider],
                );
            }

            $score = $fresh->status === CrbReport::STATUS_ADVERSE
                ? 0.0
                : $this->normalise((float) $fresh->score);

            return $this->write($user, 'crb', $weight, $score, CreditScoreComponent::STATUS_READY, $fresh->provider_reference, [
                $fresh->status === CrbReport::STATUS_ADVERSE ? 'CRB_ADVERSE_HISTORY' : 'CRB_REPORT_AVAILABLE',
            ], ['status' => $fresh->status, 'provider' => $fresh->provider]);
        }

        $legacy = CreditScore::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            })
            ->latest('created_at')
            ->first();

        if ($legacy && $legacy->score !== null) {
            return $this->write($user, 'crb', $weight, $this->normalise((float) $legacy->score), CreditScoreComponent::STATUS_READY, null, ['CRB_SCORE_AVAILABLE'], [
                'band' => $legacy->band,
                'rating' => $legacy->rating,
                'route' => 'legacy_cached',
            ]);
        }

        $route = $this->externalRoute();
        if ($route === 'cito' && ! $this->cito->configured()) {
            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['CITO_NOT_CONFIGURED']);
        }

        if ($this->shouldUseCito($route)) {
            return $this->refreshCitoScore($user, 'crb', 'CREDIT_SCORE_CRB', $weight);
        }

        if (! $this->directFallbackAllowed() && $route !== 'direct') {
            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['DIRECT_PROVIDER_FALLBACK_DISABLED']);
        }

        return $this->refreshDirectCrb($user, $weight);
    }

    private function refreshDirectCrb(User $user, float $weight): CreditScoreComponent
    {
        $baseUrl = trim((string) config('services.crb.base_url'));
        $clientId = trim((string) config('services.crb.account'));
        $clientSecret = trim((string) config('services.crb.password'));
        if ($baseUrl === '' || $clientId === '' || $clientSecret === '') {
            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['CRB_DIRECT_PROVIDER_NOT_CONFIGURED']);
        }

        $requestReference = 'OPF-DIRECT-CRB-'.Str::upper(Str::random(16));

        try {
            $token = Cache::remember('opfin:crb:access_token', now()->addMinutes(20), function () use ($baseUrl, $clientId, $clientSecret) {
                $response = Http::asForm()->timeout(8)->post(rtrim($baseUrl, '/').'/v1/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
                $response->throw();

                return (string) $response->json('access_token');
            });

            $response = Http::withToken($token)->acceptJson()->timeout(10)->post(
                rtrim($baseUrl, '/').'/v1/credit-enquiries/credit-scores',
                [
                    'entity_type' => 0,
                    'client_consented' => 'Yes',
                    'phone_number' => $user->phone,
                ],
            );
            $response->throw();
            $payload = $response->json();
            $scoring = data_get($payload, 'data.CRB.Scoring', []);
            $rawScore = data_get($scoring, 'Score');
            if ($rawScore === null) {
                $this->recordEconomics($user, 'CREDIT_SCORE_CRB', 'configured_crb', 'DIRECT_PROVIDER', $requestReference, null, 'ERROR');

                return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_ERROR, null, ['CRB_SCORE_MISSING'], [
                    'response_received' => true,
                    'route' => 'DIRECT_PROVIDER',
                ]);
            }

            CreditScore::create([
                'user_id' => $user->id,
                'score' => (int) round((float) $rawScore),
                'band' => data_get($scoring, 'Band'),
                'rating' => data_get($scoring, 'Rating'),
                'probability_of_default_percent' => data_get($scoring, 'Probability_of_Default_Percent'),
                'likelihood_to_default' => data_get($scoring, 'Likelihood_to_Default'),
                'data' => [
                    'route' => 'DIRECT_PROVIDER',
                    'provider_reference' => data_get($payload, 'reference'),
                ],
            ]);

            $report = CrbReport::create([
                'user_id' => $user->id,
                'provider' => 'configured_crb',
                'provider_reference' => data_get($payload, 'reference'),
                'status' => CrbReport::STATUS_CLEAR,
                'score' => (int) round((float) $rawScore),
                'risk_flags' => [],
                'raw_response' => ['scoring' => $scoring, 'route' => 'DIRECT_PROVIDER'],
                'requested_at' => now(),
                'received_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            $this->recordEconomics(
                $user,
                'CREDIT_SCORE_CRB',
                'configured_crb',
                'DIRECT_PROVIDER',
                $requestReference,
                $report->provider_reference,
                'PASS',
            );

            return $this->write($user, 'crb', $weight, $this->normalise((float) $rawScore), CreditScoreComponent::STATUS_READY, $report->provider_reference, [
                'CRB_SCORE_REFRESHED',
                'DIRECT_PROVIDER_ROUTE',
            ], [
                'band' => data_get($scoring, 'Band'),
                'rating' => data_get($scoring, 'Rating'),
                'route' => 'DIRECT_PROVIDER',
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->recordEconomics($user, 'CREDIT_SCORE_CRB', 'configured_crb', 'DIRECT_PROVIDER', $requestReference, null, 'ERROR');

            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_ERROR, null, ['CRB_DIRECT_PROVIDER_ERROR']);
        }
    }

    private function refreshConfiguredSource(string $source, User $user): CreditScoreComponent
    {
        $weight = (float) config("opfin.credit.scoring_weights.{$source}", $source === 'mno' ? 25 : 15);
        $route = $this->externalRoute();

        if ($source === 'mno') {
            if ($route === 'cito' && ! $this->cito->configured()) {
                return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['CITO_NOT_CONFIGURED']);
            }
            if ($this->shouldUseCito($route)) {
                return $this->refreshCitoScore($user, $source, 'CREDIT_SCORE_MNO', $weight);
            }
        }

        $url = trim((string) config("services.scoring.{$source}.url"));
        $token = trim((string) config("services.scoring.{$source}.token"));

        if ($url === '') {
            $existing = CreditScoreComponent::query()
                ->where('user_id', $user->id)
                ->where('source', $source)
                ->where('status', CreditScoreComponent::STATUS_READY)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('received_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, [strtoupper($source).'_PROVIDER_NOT_CONFIGURED']);
        }

        try {
            $phones = CustomerPhoneNumber::query()
                ->where('user_id', $user->id)
                ->whereNotNull('verified_at')
                ->pluck('phone')
                ->values()
                ->all();

            $request = Http::acceptJson()->timeout(8);
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($url, [
                'customer_reference' => (string) $user->id,
                'nin' => $user->national_id,
                'phones' => $phones,
            ]);
            $response->throw();

            $score = $response->json('score');
            if (! is_numeric($score)) {
                return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_ERROR, $response->json('reference'), [strtoupper($source).'_SCORE_MISSING']);
            }

            return $this->write(
                $user,
                $source,
                $weight,
                $this->normalise((float) $score),
                CreditScoreComponent::STATUS_READY,
                $response->json('reference'),
                array_values(array_filter((array) $response->json('reason_codes', []), 'is_string')),
                ['provider_status' => $response->json('status'), 'route' => 'DIRECT_PROVIDER'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_ERROR, null, [strtoupper($source).'_PROVIDER_ERROR']);
        }
    }

    private function refreshCitoScore(User $user, string $source, string $capability, float $weight): CreditScoreComponent
    {
        $consent = ConsentRecord::query()
            ->where('user_id', $user->id)
            ->where('purpose', ConsentRecord::PURPOSE_CREDIT_PROCESSING)
            ->where('status', ConsentRecord::STATUS_GRANTED)
            ->latest('granted_at')
            ->first();

        if (! $consent) {
            return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['CITO_CONSENT_REQUIRED']);
        }

        $localReference = 'OPF-CITO-'.Str::upper(Str::random(18));

        try {
            $payload = $this->cito->creditScore($user, $consent, $capability);
            $reference = (string) ($payload['reference'] ?? $localReference);
            $providerReference = isset($payload['providerReference']) ? (string) $payload['providerReference'] : null;
            $status = strtoupper((string) ($payload['status'] ?? 'ERROR'));
            $reasonCodes = array_values(array_filter((array) ($payload['reasonCodes'] ?? []), 'is_string'));

            $this->recordEconomics(
                $user,
                $capability,
                'cito',
                'CITO_MANAGED',
                $reference,
                $providerReference,
                $status,
                ['cito_reason_codes' => $reasonCodes],
            );

            if ($status === 'PENDING') {
                return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_PENDING, $providerReference, [
                    ...$reasonCodes,
                    'CITO_PRIMARY_ROUTE',
                ], ['route' => 'CITO_MANAGED', 'cito_reference' => $reference]);
            }

            if ($status !== 'PASS') {
                return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, $providerReference, [
                    ...$reasonCodes,
                    'CITO_CAPABILITY_NOT_AVAILABLE',
                ], ['route' => 'CITO_MANAGED', 'cito_reference' => $reference]);
            }

            $rawScore = data_get($payload, 'result.score');
            if (! is_numeric($rawScore)) {
                return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_ERROR, $providerReference, [
                    ...$reasonCodes,
                    'CITO_SCORE_MISSING',
                ], ['route' => 'CITO_MANAGED', 'cito_reference' => $reference]);
            }

            if ($source === 'crb') {
                CreditScore::create([
                    'user_id' => $user->id,
                    'score' => (int) round((float) $rawScore),
                    'band' => data_get($payload, 'result.providerBand'),
                    'rating' => data_get($payload, 'result.providerBand'),
                    'data' => [
                        'route' => 'CITO_MANAGED',
                        'cito_reference' => $reference,
                        'provider_reference' => $providerReference,
                        'reason_codes' => $reasonCodes,
                    ],
                ]);

                CrbReport::create([
                    'user_id' => $user->id,
                    'provider' => 'cito',
                    'provider_reference' => $providerReference,
                    'status' => CrbReport::STATUS_CLEAR,
                    'score' => (int) round((float) $rawScore),
                    'risk_flags' => [],
                    'raw_response' => [
                        'result' => $payload['result'] ?? [],
                        'reason_codes' => $reasonCodes,
                        'cito_reference' => $reference,
                        'route' => 'CITO_MANAGED',
                    ],
                    'requested_at' => now(),
                    'received_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]);
            }

            return $this->write(
                $user,
                $source,
                $weight,
                $this->normalise((float) $rawScore),
                CreditScoreComponent::STATUS_READY,
                $providerReference,
                array_values(array_unique([...$reasonCodes, 'CITO_PRIMARY_ROUTE'])),
                [
                    'provider_band' => data_get($payload, 'result.providerBand'),
                    'cito_reference' => $reference,
                    'route' => 'CITO_MANAGED',
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            // Do not silently fall back here. A timeout may have reached the paid provider.
            // Operations can explicitly switch OPFIN_EXTERNAL_SERVICE_ROUTE=direct after reconciliation.
            $this->recordEconomics($user, $capability, 'cito', 'CITO_MANAGED', $localReference, null, 'ERROR', [
                'automatic_direct_retry_suppressed' => true,
            ]);

            return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_ERROR, null, [
                'CITO_CAPABILITY_ERROR',
                'DIRECT_RETRY_REQUIRES_EXPLICIT_ROUTE_SWITCH',
            ]);
        }
    }

    private function recordEconomics(
        User $user,
        string $capability,
        string $provider,
        string $route,
        string $requestReference,
        ?string $providerReference,
        string $status,
        array $metadata = [],
    ): void {
        $this->economics->record([
            'user_id' => $user->id,
            'service_code' => 'credit_data',
            'capability_code' => $capability,
            'provider' => $provider,
            'route' => $route,
            'environment' => $route === 'CITO_MANAGED'
                ? (string) config('services.cito.environment', 'SANDBOX')
                : (string) config('services.crb.environment', 'PRODUCTION'),
            'request_reference' => $requestReference,
            'provider_reference' => $providerReference,
            'status' => $status,
            'currency' => 'UGX',
            'metadata' => $metadata,
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

    private function write(
        User $user,
        string $source,
        float $weight,
        ?float $score,
        string $status,
        ?string $reference = null,
        array $reasonCodes = [],
        array $rawPayload = [],
    ): CreditScoreComponent {
        return CreditScoreComponent::updateOrCreate(
            ['user_id' => $user->id, 'source' => $source, 'phone_number_id' => null],
            [
                'status' => $status,
                'score' => $score,
                'weight_percent' => $weight,
                'source_reference' => $reference,
                'reason_codes' => $reasonCodes,
                'raw_payload' => $rawPayload,
                'received_at' => $status === CreditScoreComponent::STATUS_READY ? now() : null,
                'expires_at' => $status === CreditScoreComponent::STATUS_READY ? now()->addDays(30) : null,
            ],
        );
    }

    private function normalise(float $score): float
    {
        if ($score <= 100) {
            return max(0, min(100, $score));
        }

        return max(0, min(100, round($score / 10, 2)));
    }
}
