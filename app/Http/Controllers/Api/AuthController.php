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

    // Show account deletion form
    public function showDeleteForm()
    {
        return view('account.delete');
    }

    // Process account deletion
    public function destroy(Request $request)
    {
        try {
            $request->validate([
                'phone' => ['required'],
                'password' => ['required'],
                'confirmation' => ['required', 'in:DELETE']
            ], [
                'phone.required' => 'The phone number is required.',
                'phone.exists' => 'This phone number is not registered.',
                'password.required' => 'Please enter your password.',
                'confirmation.required' => 'Confirmation is required.',
                'confirmation.in' => 'Please type "DELETE" to confirm.'
            ]);
            $user = User::where('phone', $request->phone)->first();
            if (!$user || !Hash::check($request->password, $user->password)) {
                return redirect()->back()->with('error', 'User details provided are invalid');
            }
            $user->delete();

            return redirect('/')->with('success', 'Your account has been permanently deleted.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    //to be implemented
    protected function beforeUserDelete($user)
    {
        // Anonymize user data for compliance
        $user->update([
            'email' => 'deleted-' . $user->id . '@deleted.example',
            'phone' => null,
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'deleted_at' => now(),
        ]);
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|unique:users,phone',
                'password' => ['required', 'string', 'confirmed', $this->passwordRule()],
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
            }

            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'role' => User::ROLE_CUSTOMER,
                'password' => Hash::make($request->password),
            ]);

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

        if (!$user || !Hash::check($request->password, $user->password)) {
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
            'otp' => 'required|string',
            'password' => ['required', 'string', 'confirmed', $this->passwordRule()],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return ApiResponse::error('Invalid reset request', 404);
        }

        $otpRecord = Otp::where('phone', $request->phone)->first();
        if (
            !$otpRecord ||
            Carbon::now()->greaterThan($otpRecord->expires_at) ||
            !hash_equals($otpRecord->otp, $request->otp)
        ) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        $user->password = Hash::make($request->password);
        if ($user->save()) {
            $otpRecord->delete();
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

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        Otp::updateOrCreate(
            ['phone' => $request->phone],
            ['otp' => $otp, 'expires_at' => $expiresAt]
        );

        $this->smsService->queueSms($request->phone, 'OpFin: Your otp code is ' . $otp);

        return ApiResponse::success('OTP generated successfully');
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $otpRecord = Otp::where('phone', $request->phone)->first();

        if (!$otpRecord) {
            return ApiResponse::error('Invalid or expired OTP', 404);
        }

        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        if (! hash_equals($otpRecord->otp, $request->otp)) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        return ApiResponse::success('OTP verified successfully');
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

    private function authUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'nin_status' => $user->nin_status,
            'national_id' => $user->national_id,
            'date_of_birth' => $user->date_of_birth,
        ];
    }
}
