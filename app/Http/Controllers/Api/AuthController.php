<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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

        // Optional: Cancel pending transactions
        // $user->loans()->update(['status' => 'cancelled']);

        // Add any other data cleanup needed for your financial app
    }

    public function register(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'role' => 'Member',
                'password' => Hash::make($request->password),
            ]);

            // Generate an access token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Return successful response — same shape as login
            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'credit_score' => $user->creditScore(),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function login(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find user by phone
        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'credit_score' => $user->creditScore(),
        ]);
    }

    // Reset Password
    public function resetPassword(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        // Find user by phone
        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number'
            ], 404);
        }

        $user->password = Hash::make($request->password);
        if ($user->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to reset password.'
        ]);
    }

    public function generateOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        // Store OTP in the database
        Otp::updateOrCreate(
            ['phone' => $request->phone],
            ['otp' => $otp, 'expires_at' => $expiresAt]
        );

        // Simulate sending the OTP (you can integrate an SMS gateway here)
        $this->smsService->queueSms($request->phone, 'OpFin: Your otp code is ' . $otp);

        return response()->json([
            'success' => true,
            'message' => 'OTP generated successfully',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Retrieve the OTP record
        $otpRecord = Otp::where('phone', $request->phone)->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'OTP not found for this phone number',
            ], 404);
        }

        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired',
            ], 400);
        }

        if ($otpRecord->otp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        // OTP is valid, you can perform additional actions (e.g., user login)
        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
        ]);
    }
}
