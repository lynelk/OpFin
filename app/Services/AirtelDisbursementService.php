<?php

namespace App\Services;

use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AirtelDisbursementService
{
    protected $airtel;

    public function __construct(AirtelService $airtel)
    {
        $this->airtel = $airtel;
    }

    public function disburse(Transaction $transaction)
    {
        $baseUrl = config('services.airtel.base_url');
        $currency = config('services.airtel.currency');

        $token = $this->airtel->getAccessToken();
        $txnReference = Str::uuid()->toString();
        $phoneNumber = $transaction->phone;
        $amount = $transaction->amount;
        $payload = [
            "payee" => [
                "currency" => $currency,
                "msisdn" => str($phoneNumber)->after('256')->toString(),
                "wallet_type" => 'NORMAL',
            ],
            "reference" => 'Loan Disbursement',
            "pin" => $this->encrypt(config('services.airtel.pin')),
            "transaction" => [
                "amount" => (int) $amount,
                "id" => $txnReference,
                "type" => "B2C",
            ],
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
            ->post("{$baseUrl}/standard/v2/disbursements/", $payload);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'Request failed',
                'response' => $response->body(),
            ];
        }

        $data = $response->json();

        return [
            'success' => $data['status']['success'] ?? false,
            'message' => $data['data']['transaction']['message'] ?? ($data['status']['message'] ?? 'Unknown'),
            'transaction_id' => $data['data']['transaction']['id'] ?? null,
            'status' => 'PENDING',
            'airtel_money_id' => $data['data']['transaction']['airtel_money_id'] ?? null,
        ];
    }

    public function checkStatus($transactionReference)
    {
        $baseUrl = config('services.airtel.base_url');
        $token = $this->airtel->getAccessToken();

        $url = "{$baseUrl}/standard/v2/disbursements/{$transactionReference}?transactionType=B2C";

        $response = Http::withHeaders([
            'Accept'      => '*/*',
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

    public function encrypt($data): string
    {
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . config('services.airtel.public_key') . "\n-----END PUBLIC KEY-----";

        $publicKey = openssl_pkey_get_public($public_key);

        if (! $publicKey) {
            throw new Exception('Public key malformed.');
        }

        if (! openssl_public_encrypt($data, $encrypted, $publicKey)) {
            throw new Exception('Error encrypting with public key');
        }

        return base64_encode($encrypted);
    }
}
