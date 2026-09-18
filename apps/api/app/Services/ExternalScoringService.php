<?php

namespace App\Services;

use App\Models\CrbReport;
use App\Models\CreditScore;
use App\Models\CreditScoreComponent;
use App\Models\CustomerPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExternalScoringService
{
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
            $score = $fresh->status === CrbReport::STATUS_ADVERSE
                ? 0.0
                : $this->normalise((float) ($fresh->score ?? 0));

            return $this->write($user, 'crb', $weight, $score, CreditScoreComponent::STATUS_READY, $fresh->provider_reference, [
                $fresh->status === CrbReport::STATUS_ADVERSE ? 'CRB_ADVERSE_HISTORY' : 'CRB_REPORT_AVAILABLE',
            ], ['status' => $fresh->status]);
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
            ]);
        }

        $baseUrl = trim((string) config('services.crb.base_url'));
        $clientId = trim((string) config('services.crb.account'));
        $clientSecret = trim((string) config('services.crb.password'));
        if ($baseUrl === '' || $clientId === '' || $clientSecret === '') {
            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_UNAVAILABLE, null, ['CRB_PROVIDER_NOT_CONFIGURED']);
        }

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
                return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_ERROR, null, ['CRB_SCORE_MISSING'], ['response_received' => true]);
            }

            CreditScore::create([
                'user_id' => $user->id,
                'score' => (int) round((float) $rawScore),
                'band' => data_get($scoring, 'Band'),
                'rating' => data_get($scoring, 'Rating'),
                'probability_of_default_percent' => data_get($scoring, 'Probability_of_Default_Percent'),
                'likelihood_to_default' => data_get($scoring, 'Likelihood_to_Default'),
                'data' => $payload,
            ]);

            $report = CrbReport::create([
                'user_id' => $user->id,
                'provider' => 'configured_crb',
                'provider_reference' => data_get($payload, 'reference'),
                'status' => CrbReport::STATUS_CLEAR,
                'score' => (int) round((float) $rawScore),
                'risk_flags' => [],
                'raw_response' => ['scoring' => $scoring],
                'requested_at' => now(),
                'received_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);

            return $this->write($user, 'crb', $weight, $this->normalise((float) $rawScore), CreditScoreComponent::STATUS_READY, $report->provider_reference, ['CRB_SCORE_REFRESHED'], [
                'band' => data_get($scoring, 'Band'),
                'rating' => data_get($scoring, 'Rating'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->write($user, 'crb', $weight, null, CreditScoreComponent::STATUS_ERROR, null, ['CRB_PROVIDER_ERROR']);
        }
    }

    private function refreshConfiguredSource(string $source, User $user): CreditScoreComponent
    {
        $weight = (float) config("opfin.credit.scoring_weights.{$source}", $source === 'mno' ? 25 : 15);
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
                ['provider_status' => $response->json('status')],
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->write($user, $source, $weight, null, CreditScoreComponent::STATUS_ERROR, null, [strtoupper($source).'_PROVIDER_ERROR']);
        }
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
