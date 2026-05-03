<?php

namespace App\Services;

use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CitotechPaymentService
{

    public function disburse(string $phone, float $amount, string $reference)
    {
        // Request data
        $data = [
            "merchant_number" => config('services.cpay.account'),
            "payee_number" => $phone,
            "reference" => $reference,
            "amount" => $amount,
            "description" => "Loan Disbursement",
            "callback_url" => route('handleCallback'),

        ];
        // Compute the signature
        try {
            // Concatenate the fields in the specified order
            $signatureData = $data['merchant_number'] .
                $data['payee_number'] .
                $data['amount'] .
                $data['reference'] .
                $data['description'];

            // Load the private key (store this securely in your .env or config)
            $privateKey = config('services.cpay.password');
            if (!$privateKey) {
                throw new \Exception("Private key not configured");
            }

            // Sign the data
            openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            // Base64 encode the signature
            $base64Signature = base64_encode($signature);

            // Add signature to the request data
            $data['signature'] = $base64Signature;
        } catch (\Exception $e) {
            Log::error("Signature generation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Signature generation failed'];
        }

        // Send the request
        try {
            $response = Http::post(config('services.cpay.base_url') . '/doMobileMoneyPayOut', $data);

            if ($response->successful()) {
                $responseData = $response->json();

                // Validate response structure
                if (isset($responseData['state']) && $responseData['state'] === 'OK') {
                    return [
                        'success' => true,
                        'transaction_id' => $responseData['txDetails']['uniqueTransactionId'] ?? null,
                        'status' => $responseData['txDetails']['transactionStatus'] ?? null,
                        'network_reference' => $responseData['txDetails']['networkRef'] ?? null,
                        'message' => $responseData['message'] ?? 'Operation was successful'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Unexpected response format',
                    'response' => $responseData
                ];
            } else {
                Log::error("API request failed", ['response' => $response->body()]);
                return ['success' => false, 'message' => 'API request failed', 'response' => $response->json()];
            }
        } catch (\Exception $e) {
            Log::error("API request exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'API request exception'];
        }
    }

    public function collect(Transaction $transaction): array
    {
        try {
            $apiUrl = config('services.cpay.base_url') . '/doMobileMoneyPayIn';
            $merchantNumber = config('services.cpay.account');
            $callBackUrl = route('handleCallback');
            // Prepare the request data
            $requestData = [
                'merchant_number' => $merchantNumber,
                'payer_number' => $transaction->phone,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
                'description' => $transaction->type,
            ];

            // Generate signature if required
            $requestData['signature'] = $this->generateSignature(implode($requestData));
            $requestData['callback_url'] = $callBackUrl;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30) // 30 seconds timeout
                ->post($apiUrl, $requestData);
            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['state'] === 'OK') {
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'transaction_id' => $responseData['txDetails']['uniqueTransactionId'] ?? null,
                        'status' => $responseData['txDetails']['transactionStatus'] ?? null
                    ];
                }

                return [
                    'success' => false,
                    'error' => $responseData['message'] ?? 'API returned error',
                    'code' => $responseData['code'] ?? 'UNKNOWN'
                ];
            }

            return [
                'success' => false,
                'error' => 'API request failed',
                'status_code' => $response->status(),
                'response' => $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Mobile Money PayIn failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    protected function generateSignature($signatureData): string
    {
        // Load the private key (store this securely in your .env or config)
        $privateKey = config('services.cpay.password');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        return base64_encode($signature);
    }

    public function getBalances()
    {
        // Concatenate the fields in the specified order
        $signatureData = config('services.cpay.account');

        // Load the private key (store this securely in your .env or config)
        $privateKey = config('services.cpay.password');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        $base64Signature = base64_encode($signature);

        // Add signature to the request data
        $signature = $base64Signature;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(config('services.cpay.base_url') . '/doGetBalances', [
                'merchant_number' => config('services.cpay.account'),
                'signature' => $signature,
            ]);
            if ($response->successful()) {
                $data = $response->json();

                if ($data['state'] === 'OK') {
                    return [
                        'success' => true,
                        'balances' => $data['balances'],
                        'message' => $data['message']
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'API returned error'
                ];
            }

            return [
                'success' => false,
                'message' => 'API request failed with status: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Balance check failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error making API request: ' . $e->getMessage()
            ];
        }
        //use like belows;
        // $phone = User::find($request->user_id)->phone;
        //         if (str_starts_with($phone, '25676') || str_starts_with($phone, '25677') || str_starts_with($phone, '25678')) {
        //             $network  = 'MTNMM';
        //         } else if (str_starts_with($phone, '25670') || str_starts_with($phone, '25674') || str_starts_with($phone, '25675')) {
        //             $network  = 'AIRTELMM';
        //         } else {
        //             $network = null;
        //         }

        //         $accountBalance = 0;
        //         $getBalancesResponse = $this->getBalances();
        //         if ($getBalancesResponse['success']) {
        //             $data = collect($getBalancesResponse['balances']);
        //             $accountBalance = $data->firstWhere('name', $network)['amount'] ?? null;
        //         }
        //         if ($request->amount > $accountBalance) {
        //             $accountBalance -= 2000;
        //             return response()->json([
        //                 'success' => false,
        //                 'message' => 'We cannot process this request at this time, please try an amount less than UGX ' . number_format($accountBalance)
        //             ], 400);
        //         }
    }

    public function checkStatus(Transaction $transaction)
    {
        $merchantNumber = config('services.cpay.account');
        $reference = $transaction->reference;

        $signatureData = $merchantNumber . $reference;
        $privateKey = config('services.cpay.password');

        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $base64Signature = base64_encode($signature);

        $url = config('services.cpay.base_url') . '/doTransactionCheckStatus';
        $data = [
            'merchant_number' => $merchantNumber,
            'reference' => $reference,
            'signature' => $base64Signature,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $data);

        if (!$response->successful()) {
            throw new \Exception("Old gateway status check failed: " . $response->body());
        }

        $data = $response->json();
        if ($data['state'] !== 'OK') {
            return [
                'success' => false,
                'status' => 'FAILED',
            ];
        }

        return [
            'success' => true,
            'status' => $data['txDetails']['transactionStatus'],
            'network_reference' => $data['txDetails']['networkRef'] ?? null,
        ];
    }
}
