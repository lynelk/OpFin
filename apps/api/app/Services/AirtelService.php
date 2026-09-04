<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AirtelService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.airtel.client_id');
        $this->clientSecret = config('services.airtel.client_secret');
        $this->baseUrl = config('services.airtel.base_url');
    }

    public function getAccessToken()
    {
        $response = Http::asJson()->post("{$this->baseUrl}/auth/oauth2/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $data = $response->json();

        if ($response->successful()) {
            return $data['access_token'];
        }

        throw new \Exception('Failed to obtain Airtel access token: '.json_encode($data));
    }

    /**
     * KYC lookup only. Airtel money movement is intentionally not implemented in OpFin.
     */
    public function getKycInfo(string $msisdn)
    {
        $token = $this->getAccessToken();
        $cleanMsisdn = str($msisdn)->after('256')->toString();
        $url = "{$this->baseUrl}/standard/v1/users/{$cleanMsisdn}";

        $response = Http::withHeaders([
            'Accept' => '*/*',
            'Authorization' => "Bearer {$token}",
            'X-Country' => config('services.airtel.country', 'UG'),
            'X-Currency' => config('services.airtel.currency', 'UGX'),
        ])->get($url);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'Request failed',
                'response' => $response->body(),
            ];
        }

        return $response->json();
    }
}
