<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
use App\Models\Otp;
use App\Services\CustomerCreditProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CustomerPhoneController extends Controller
{
    public function __construct(private readonly CustomerCreditProfileService $profiles) {}

    public function index(Request $request): JsonResponse
    {
        $this->profiles->ensurePrimaryPhone($request->user());

        return ApiResponse::success('Phone numbers loaded.', [
            'phone_numbers' => CustomerPhoneNumber::query()
                ->where('user_id', $request->user()->id)
                ->orderByRaw("CASE WHEN kind = 'primary' THEN 0 ELSE 1 END")
                ->get(),
            'secondary_phone_required' => false,
        ]);
    }

    public function verifySecondary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:32|unique:customer_phone_numbers,phone',
            'verification_token' => 'required|string|size:64',
            'provider' => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $otp = Otp::where('phone', $request->string('phone'))->first();
        $valid = $otp
            && $otp->verified_at
            && $otp->verification_token_hash
            && $otp->verified_at->gte(now()->subMinutes(10))
            && hash_equals($otp->verification_token_hash, hash('sha256', (string) $request->input('verification_token')));

        if (! $valid) {
            return ApiResponse::error('Verify this phone with the OTP sent to it before adding it.', 422);
        }

        $phone = CustomerPhoneNumber::create([
            'user_id' => $request->user()->id,
            'phone' => (string) $request->input('phone'),
            'kind' => 'secondary',
            'provider' => $request->input('provider'),
            'ownership_name_match_status' => 'otp_verified',
            'verified_at' => now(),
        ]);

        CustomerWallet::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'provider' => $request->input('provider', 'mobile_money'),
                'msisdn' => $phone->phone,
            ],
            [
                'phone_number_id' => $phone->id,
                'status' => 'active',
                'verified_at' => now(),
            ],
        );

        $otp->delete();
        $profile = $this->profiles->refresh($request->user(), true);

        return ApiResponse::success('Second phone verified and added.', [
            'phone_number' => $phone,
            'credit_profile' => $profile,
        ], 201);
    }
}
