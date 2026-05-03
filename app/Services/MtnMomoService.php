<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MtnMomoService
{
    protected $baseUrl;
    protected $currency;

    public function __construct()
    {
        $this->baseUrl = config('services.mtn.base_url');
        $this->currency = config('services.mtn.currency');
    }

    private function getToken($subscriptionKey, $userId, $apiKey)
    {
        $type = $subscriptionKey === config('services.mtn.collection_sub_key')
            ? 'collection'
            : 'disbursement';

        // unique cache key for each type
        $cacheKey = "mtn_momo_token_{$type}";

        // check if token exists in cache
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }

        // request new token
        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
        ])->withBasicAuth($userId, $apiKey)
            ->post("{$this->baseUrl}/{$type}/token/");

        if (!$response->successful()) {
            Log::error("MTN MoMo Token Error ({$type})", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 3600); // defaults to 1 hour

        // cache token slightly less than expiry (e.g. 58 minutes)
        cache()->put($cacheKey, $token, now()->addSeconds($expiresIn - 120));

        return $token;
    }


    public function collect($phone, $amount, $externalId, $payerMessage, $payeeNote)
    {
        try {
            $token = $this->getToken(
                config('services.mtn.collection_sub_key'),
                config('services.mtn.api_user'),
                config('services.mtn.api_key'),
            );
            $payload = [
                'amount' => $amount,
                'currency' => $this->currency,
                'externalId' => $externalId,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => $payerMessage,
                'payeeNote' => $payeeNote,
            ];
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'X-Reference-Id' => $externalId,
                'X-Callback-Url' => config('services.mtn.callback_url'),
                'X-Target-Environment' => config('services.mtn.target_env'),
                'Ocp-Apim-Subscription-Key' => config('services.mtn.collection_sub_key'),
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/collection/v1_0/requesttopay", $payload);
            if ($response->status() === 202) {
                // MTN returns 202 (Accepted) — means request received, waiting for user action.
                return [
                    'success' => true,
                    'status' => 'PENDING',
                    'message' => 'Payment request initiated successfully. Please complete the payment on your phone.',
                    'transaction_id' => $externalId,
                ];
            }

            Log::error('MTN Collection Failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => $response->body(),
                'transaction_id' => $externalId,
            ];
        } catch (\Exception $e) {
            Log::error('MTN Collection Error: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
                'transaction_id' => null,
            ];
        }
    }

    public function disburse($phone, $amount, $externalId, $message)
    {
        try {
            $token = $this->getToken(
                config('services.mtn.disbursement_sub_key'),
                config('services.mtn.api_user'),
                config('services.mtn.api_key'),
            );
            $payload = [
                'amount' => $amount,
                'currency' => $this->currency,
                'externalId' => $externalId,
                'payee' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $phone,
                ],
                'payerMessage' => $message,
                'payeeNote' => $message,
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'X-Reference-Id' => $externalId,
                'X-Callback-Url' => config('services.mtn.callback_url'),
                'X-Target-Environment' => config('services.mtn.target_env'),
                'Ocp-Apim-Subscription-Key' => config('services.mtn.disbursement_sub_key'),
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/disbursement/v1_0/transfer", $payload);
            if ($response->status() === 202) {
                // MTN returns 202 Accepted immediately; actual status will come later
                return [
                    'success' => true,
                    'status' => 'PENDING',
                    'message' => 'Transaction initiated successfully',
                    'transaction_id' => $externalId,
                ];
            }

            Log::error('MTN Disbursement Failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => $response->body(),
                'transaction_id' => $externalId,
            ];
        } catch (\Exception $e) {
            Log::error('MTN Disbursement Error: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'FAILED',
                'message' => $e->getMessage(),
                'transaction_id' => null,
            ];
        }
    }

    public function checkStatus($externalId, $type = 'collection')
    {
        try {
            $subscriptionKey = $type === 'collection'
                ? config('services.mtn.collection_sub_key')
                : config('services.mtn.disbursement_sub_key');

            $operation = $type === 'collection' ? 'requesttopay' : 'transfer';
            $token = $this->getToken(
                $subscriptionKey,
                config('services.mtn.api_user'),
                config('services.mtn.api_key'),
            );

            $url = "{$this->baseUrl}/{$type}/v1_0/{$operation}/{$externalId}";
            $response = Http::withHeaders([
                'Authorization' => "Bearer $token",
                'X-Target-Environment' => config('services.mtn.target_env'),
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])->get($url);

            if ($response->status() === 200) {
                $data = $response->json();

                return [
                    'success' => true,
                    'status' => $data['status'],
                    'transaction_id' => $data['financialTransactionId'] ?? null,
                    'message' => 'Status fetched successfully',
                    'raw' => $data
                ];
            }

            return [
                'success' => false,
                'status' => 'FAILED',
                'transaction_id' => null,
                'message' => $response->body(),
                'raw' => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'transaction_id' => null,
                'message' => $e->getMessage(),
                'raw' => null
            ];
        }
    }
    public function getBalance($type): array
    {
        try {
            $subscriptionKey = config("services.mtn.{$type}_sub_key");
            $token = $this->getToken(
                $subscriptionKey,
                config('services.mtn.api_user'),
                config('services.mtn.api_key')
            );

            if (!$token) {
                throw new \Exception('Failed to retrieve MTN MoMo token');
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                'X-Target-Environment' => config('services.mtn.target_env'),
            ])->get("{$this->baseUrl}/{$type}/v1_0/account/balance");

            if (!$response->successful()) {
                Log::error('MTN Balance API failed', [
                    'type' => $type,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => $response->body(),
                ];
            }

            return [
                'success' => true,
                'balance' => $response->json('availableBalance'),
                'currency' => $response->json('currency'),
                'raw' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('MTN Balance Error', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
