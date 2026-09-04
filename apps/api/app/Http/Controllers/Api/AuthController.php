<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\SmsService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(protected SmsService $smsService) {}

    public function showDeleteForm()
    {
        return view('account.delete');
    }

    public function destroy(Request $request)
    {
        try {
            $request->validate([
                'phone' => ['required'],
                'password' => ['required'],
                'confirmation' => ['required', 'in:DELETE'],
            ]);

            $user = User::where('phone', $request->phone)->first();
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return redirect()->back()->with('error', 'User details provided are invalid');
            }

            $this->beforeUserDelete($user);
            $user->tokens()->delete();
            $user->delete();

            return redirect('/')->with('success', 'Your account has been closed.');
        } catch (Exception $e) {
            report($e);

            return redirect()->back()->with('error', 'Unable to close the account.');
        }
    }

    protected function beforeUserDelete(User $user): void
    {
        $user->forceFill([
            'email' => 'deleted-'.$user->id.'@deleted.example',
            'phone' => 'deleted-'.$user->id,
            'name' => 'Deleted User',
        ])->save();
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|unique:users,phone',
                'verification_token' => 'required|string|size:64',
                'password' => ['required', 'string', 'confirmed', $this->passwordRule()],
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
            }

            $otpRecord = Otp::where('phone', $request->phone)->first();
            if (! $this->hasValidVerificationToken($otpRecord, (string) $request->verification_token)) {
                return ApiResponse::error('Phone verification is required before registration.', 422);
            }

            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'phone_verified_at' => now(),
                'role' => User::ROLE_CUSTOMER,
                'password' => Hash::make($request->password),
            ]);

            $otpRecord?->delete();
            $token = $this->createAccessToken($user);

            return ApiResponse::success('Registration successful', [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->authUserPayload($user),
                'credit_score' => $user->creditScore(),
            ], 201);
        } catch (Exception $e) {
            report($e);

            return ApiResponse::error('Registration failed.', 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $token = $this->createAccessToken($user);

        return ApiResponse::success('Login successful', [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
            'credit_score' => $user->creditScore(),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'string', 'confirmed', $this->passwordRule()],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $user = User::where('phone', $request->phone)->first();
        if (! $user) {
            return ApiResponse::error('Invalid reset request', 404);
        }

        $otpRecord = Otp::where('phone', $request->phone)->first();
        if (! $this->otpMatches($otpRecord, (string) $request->otp)) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        $user->password = Hash::make($request->password);
        if ($user->save()) {
            $otpRecord?->delete();
            $user->tokens()->delete();

            return ApiResponse::success('Password has been reset successfully.');
        }

        return ApiResponse::error('Failed to reset password.', 500);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully.');
    }

    public function generateOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        Otp::updateOrCreate(
            ['phone' => $request->phone],
            [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt,
                'verified_at' => null,
                'verification_token_hash' => null,
            ]
        );

        $this->smsService->queueSms($request->phone, 'OpFin: Your OTP code is '.$otp.'. It expires in 5 minutes.');

        return ApiResponse::success('OTP generated successfully', [
            'expires_at' => $expiresAt->toIso8601String(),
            'max_attempts' => 3,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $otpRecord = Otp::where('phone', $request->phone)->first();
        if (! $otpRecord || Carbon::now()->greaterThan($otpRecord->expires_at)) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        if ($otpRecord->attempts >= 3) {
            return ApiResponse::error('Maximum OTP attempts reached. Request a new code.', 429);
        }

        if (! $this->otpValueMatches($otpRecord, (string) $request->otp)) {
            $otpRecord->increment('attempts');
            $otpRecord->refresh();

            return ApiResponse::error('Invalid or expired OTP', 400, [
                'attempts_remaining' => max(0, 3 - $otpRecord->attempts),
            ]);
        }

        $verificationToken = bin2hex(random_bytes(32));
        $otpRecord->forceFill([
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
        ])->save();

        return ApiResponse::success('OTP verified successfully', [
            'verification_token' => $verificationToken,
            'verification_expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);
    }

    private function passwordRule(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }

    private function createAccessToken(User $user): string
    {
        return $user->createToken(
            'auth_token',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 10080))
        )->plainTextToken;
    }

    private function hasValidVerificationToken(?Otp $otpRecord, string $token): bool
    {
        if (! $otpRecord || ! $otpRecord->verified_at || ! $otpRecord->verification_token_hash) {
            return false;
        }

        if ($otpRecord->verified_at->lt(now()->subMinutes(10))) {
            return false;
        }

        return hash_equals($otpRecord->verification_token_hash, hash('sha256', $token));
    }

    private function otpMatches(?Otp $otpRecord, string $otp): bool
    {
        if (! $otpRecord || Carbon::now()->greaterThan($otpRecord->expires_at) || $otpRecord->attempts >= 3) {
            return false;
        }

        if (! $this->otpValueMatches($otpRecord, $otp)) {
            $otpRecord->increment('attempts');

            return false;
        }

        return true;
    }

    private function otpValueMatches(Otp $otpRecord, string $otp): bool
    {
        $stored = (string) $otpRecord->otp;

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
            return Hash::check($otp, $stored);
        }

        // Transitional compatibility for OTP rows created before hashed OTP storage was introduced.
        // Legacy values are short-lived, consumed on success, and all newly generated codes are hashed.
        return hash_equals($stored, $otp);
    }

    private function authUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_verified_at' => $user->phone_verified_at,
            'role' => $user->role,
            'nin_status' => $user->nin_status,
            'national_id' => $user->national_id,
            'date_of_birth' => $user->date_of_birth,
        ];
    }
}
