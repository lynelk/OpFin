<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditScore;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NinValidationController extends Controller
{
    protected function getAccessToken(): string
    {
        $accessToken = Cache::get('api_access_token');
        if ($accessToken) {
            return $accessToken;
        } else {
            // Step 1: Get the access token
            $tokenResponse = Http::asForm()->post(config('services.crb.base_url') . '/v1/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.crb.account'),
                'client_secret' => config('services.crb.password'),
            ]);
            if ($tokenResponse->successful()) {
                $accessToken = $tokenResponse->json()['access_token'];
                $tokenLifeTime = $tokenResponse->json()['expires_in']; // seconds
                Cache::put('api_access_token', $accessToken, $tokenLifeTime);
                return $accessToken;
            } else {
                Log::error('Failed to retrieve access token: ' . $tokenResponse->body());
                throw new Exception('Failed to get access token');
            }
        }
    }

    public function validateNin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nin' => 'required|string|max:14|min:14',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => json_encode($validator->errors()),
            ]);
        }
        try {
            $nin = $request->nin;
            $userId = $request->user_id;
            $accessToken = $this->getAccessToken();
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->post(config('services.crb.base_url') . '/v1/validate-nin', [
                'nin' => $nin,
            ]);
            if ($response->successful()) {
                $user = User::find($userId);
                $data = $response->json();
                $validationDetails = Arr::get($data, 'validation', []);
                $validationDetails = collect($validationDetails);
                $user->national_id = $validationDetails->get('nin');
                $user->date_of_birth = $validationDetails->get('date_of_birth');
                $user->nin_status = $validationDetails->get('nin_status');
                $user->api_status = $validationDetails->get('status');
                $user->validated_at = $validationDetails->get('timestamp');
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'NIN validated and user updated successfully.',
                    'data' => $validationDetails,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to validate NIN: ' . $response->body(),
                ], $response->status());
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate NIN: ' . $e->getMessage(),
            ]);
        }
    }

    function getCurrentCreditScore(int $userId, int $days = 30): ?CreditScore
    {
        $thirtyDaysAgo = now()->subDays($days);
        return CreditScore::where('user_id', $userId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->latest('created_at')
            ->first();
    }

    public function creditScores(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|exists:users,phone',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => json_encode($validator->errors()),
            ]);
        }
        try {
            $userId = $request->user_id;
            // 1. Check DB first
            $cachedScore = $this->getCurrentCreditScore($userId, 30);

            if ($cachedScore) {
                return response()->json([
                    'success' => true,
                    'message' => 'Credit scores retrieved successfully.',
                    'data' => $cachedScore->data,
                ]);
            }
            $accessToken = $this->getAccessToken();
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ])->post(config('services.crb.base_url') . '/v1/credit-enquiries/credit-scores', [
                'entity_type' => 0,
                'client_consented' => 'Yes',
                'phone_number' => $request->phone_number,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $crbScoring = $data['data']['CRB']['Scoring'] ?? [];

                CreditScore::create([
                    'user_id' => $userId, // optional
                    'score' => $crbScoring['Score'] ?? null,
                    'band' => $crbScoring['Band'] ?? null,
                    'rating' => $crbScoring['Rating'] ?? null,
                    'probability_of_default_percent' => $crbScoring['Probability_of_Default_Percent'] ?? null,
                    'likelihood_to_default' => $crbScoring['Likelihood_to_Default'] ?? null,
                    // store full payload
                    'data' => $data,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Credit scores retrieved successfully.',
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $response->body(),
                ], $response->status());
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate NIN: ' . $e->getMessage(),
            ]);
        }
    }
}
