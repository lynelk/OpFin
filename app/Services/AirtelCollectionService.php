<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtelCollectionService
{
    protected $airtel;

    public function __construct(AirtelService $airtel)
    {
        $this->airtel = $airtel;
    }

    public function collect(Transaction $transaction)
    {
        $baseUrl = config('services.airtel.base_url');
        $country = config('services.airtel.country');
        $currency = config('services.airtel.currency');

        $phoneNumber = $transaction->phone;
        $token = $this->airtel->getAccessToken();
        $txnReference = $transaction->reference;
        $reason = $transaction->type;
        $amount = $transaction->amount;

        $payload = [
            "subscriber" => [
                "country" => $country,
                "currency" => $currency,
                "msisdn" => str($phoneNumber)->after('256')->toString(),
            ],
            "transaction" => [
                "amount" => (int)$amount,
                "country" => $country,
                "currency" => $currency,
                "id" => $txnReference,
            ],
            "reference" => $reason,
        ];
        $payloadString = json_encode($payload);

        $signature = base64_encode(
            hash_hmac('sha256', $payloadString, config('services.airtel.client_secret'), true)
        );

        $publicKey = config('services.airtel.public_key');

        $response = Http::withHeaders([
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-Country'     => 'UG',
            'X-Currency'    => 'UGX',
            'Authorization' => "Bearer {$token}",
            'x-signature'   => $signature,
            'x-key'         => $publicKey,
        ])
            ->post("{$baseUrl}/merchant/v2/payments/", $payload);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'Request failed',
                'response' => $response->body(),
            ];
        }

        $data = $response->json();

        return [
            'success' => $data['status']['success'],
            'message' => $data['status']['message'],
            'status' => 'PENDING',
        ];
    }

    public function checkStatus($transactionReference)
    {
        $url = config('services.airtel.base_url') . '/standard/v1/payments/' . $transactionReference;
        $token = $this->airtel->getAccessToken();

        $response = Http::withHeaders([
            'Accept'      => 'application/json',
            'Authorization' => "Bearer {$token}",
            'X-Country'   => config('services.airtel.country', 'UG'),
            'X-Currency'  => config('services.airtel.currency', 'UGX'),
        ])->get($url);

        $data = $response->json();
        if (!$response->successful()) {
            throw new \Exception("Failed to check Airtel status: " . $response->body());
        }

        // Expected Airtel structure:
        // "data.transaction.status" = "TS" means success
        $statusCode = $data['data']['transaction']['status'] ?? null;
        $airtelMoneyId = $data['data']['transaction']['airtel_money_id'] ?? null;
        $message = $data['data']['transaction']['message'] ?? null;

        // Map Airtel statuses to your internal system
        $status = match ($statusCode) {
            'TE' => 'SUCCESSFUL',
            'TS' => 'SUCCESSFUL',
            'TF' => 'FAILED',
            default => 'PENDING'
        };

        return [
            'success' => true,
            'status' => $status,
            'message' => $message,
            'network_reference' => $airtelMoneyId,
        ];
    }
}
