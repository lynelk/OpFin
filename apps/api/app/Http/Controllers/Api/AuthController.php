<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\Otp;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CommercialInsightsService;
use App\Services\CustomerCreditProfileService;
use App\Services\PersonalFinancialSpaceService;
use App\Services\SmsService;
use App\Services\Cito\CitoFeatureGate;
use App\Services\Cito\CitoOtpAuthService;
use App\Services\StaffCredentialService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(
        protected SmsService $smsService,
        private readonly CustomerCreditProfileService $profiles,
        private readonly CommercialInsightsService $commercialInsights,
        private readonly PersonalFinancialSpaceService $personalSpaces,
    ) {}

    /**
     * The Web app owns public account deletion and routes it through
     * AccountDeletionService. This legacy URL only forwards there.
     */
    public function redirectToAccountDeletion()
    {
        $web = rtrim((string) config('services.opfin.web_url'), '/');
        abort_if($web === '', 404);

        return redirect()->away($web.'/account/delete', 303);
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'nullable|string|max:80',
                'other_name' => 'nullable|string|max:80',
                'last_name' => 'nullable|string|max:80',
                'name' => 'nullable|string|max:255',
                'phone' => 'required|string|max:32|unique:users,phone',
                'verification_token' => 'required|string|size:64',
                'pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
                'pin_confirmation' => 'nullable|string|same:pin',
                'password' => ['nullable', 'string'],
                'password_confirmation' => 'nullable|string|same:password',
                'terms_accepted' => 'nullable|accepted',
                'preferred_language' => 'nullable|string|max:16',
                'accessibility_preferences' => 'nullable|array',
                'acquisition_channel' => ['nullable', Rule::in(CommercialInsightsService::ACQUISITION_CHANNELS)],
                'acquisition_source' => 'nullable|string|max:120',
                'acquisition_campaign' => 'nullable|string|max:160',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
            }

            $first = trim((string) $request->input('first_name'));
            $other = trim((string) $request->input('other_name'));
            $last = trim((string) $request->input('last_name'));
            $legacyName = trim((string) $request->input('name'));

            if (($first === '' || $last === '') && $legacyName === '') {
                return ApiResponse::error('First name and last name are required.', 422, [
                    'first_name' => ['Enter your first name.'],
                    'last_name' => ['Enter your last name.'],
                ]);
            }

            $pin = (string) $request->input('pin');
            $legacyPassword = (string) $request->input('password');
            if ($pin === '' && $legacyPassword === '') {
                return ApiResponse::error('Create a 6-digit PIN.', 422, ['pin' => ['PIN is required.']]);
            }
            if ($pin !== '' && $this->weakPin($pin)) {
                return ApiResponse::error('Choose a less predictable 6-digit PIN.', 422, ['pin' => ['Avoid repeated or sequential numbers.']]);
            }
            if ($pin !== '' && $pin !== (string) $request->input('pin_confirmation')) {
                return ApiResponse::error('PIN confirmation does not match.', 422, ['pin_confirmation' => ['PINs do not match.']]);
            }
            if ($legacyPassword !== '') {
                $legacyValidator = Validator::make($request->all(), [
                    'password' => ['required', 'confirmed', $this->passwordRule()],
                ]);
                if ($legacyValidator->fails()) {
                    return ApiResponse::error('Validation failed.', 422, $legacyValidator->errors()->toArray());
                }
            }

            $otpRecord = Otp::where('phone', $request->phone)->first();
            if (! $this->hasValidVerificationToken($otpRecord, (string) $request->verification_token)) {
                return ApiResponse::error('Phone verification is required before registration.', 422);
            }

            $name = $legacyName !== ''
                ? $legacyName
                : trim(implode(' ', array_filter([$first, $other, $last])));

            $user = User::create([
                'name' => $name,
                'first_name' => $first !== '' ? $first : null,
                'other_name' => $other !== '' ? $other : null,
                'last_name' => $last !== '' ? $last : null,
                'phone' => $request->phone,
                'phone_verified_at' => now(),
                'role' => User::ROLE_CUSTOMER,
                'password' => Hash::make($pin !== '' ? $pin : $legacyPassword),
                'preferred_language' => $request->input('preferred_language', 'en'),
                'accessibility_preferences' => $request->input('accessibility_preferences'),
            ]);

            $otpRecord?->delete();
            try {
                $this->commercialInsights->recordAttribution($user, [
                    'acquisition_channel' => $request->input('acquisition_channel', 'other'),
                    'source' => $request->input('acquisition_source', 'unattributed_registration'),
                    'campaign' => $request->input('acquisition_campaign'),
                    'acquired_at' => now(),
                    'metadata' => [
                        'capture' => 'registration',
                        'explicit_channel' => $request->filled('acquisition_channel'),
                    ],
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }

            $this->personalSpaces->ensure($user);
            $this->profiles->ensurePrimaryPhone($user);
            $this->profiles->refresh($user, false);
            $token = $this->createAccessToken($user);

            return ApiResponse::success('Registration successful', [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->authUserPayload($user),
                'credit_profile' => $this->profiles->status($user),
            ], 201);
        } catch (Exception $e) {
            report($e);

            return ApiResponse::error('Registration failed.', 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|nullable|string',
            'email' => 'required_without:phone|nullable|email|max:254',
            'pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        // Staff may sign in with their email and password; customers always use phone and PIN.
        $email = $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null;
        $credential = (string) ($request->input('pin') ?: $request->input('password'));
        if ($credential === '') {
            return $email !== null
                ? ApiResponse::error('Enter your password.', 422, ['password' => ['Password is required.']])
                : ApiResponse::error('Enter your 6-digit PIN.', 422, ['pin' => ['PIN is required.']]);
        }

        $key = 'opfin-login:'.sha1(($email !== null ? 'email:'.$email : (string) $request->phone).'|'.(string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return ApiResponse::error('Too many sign-in attempts. Try again shortly or reset your PIN.', 429, [
                'retry_after_seconds' => RateLimiter::availableIn($key),
            ]);
        }

        $user = $email !== null ? $this->staffByEmail($email) : User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($credential, $user->password)) {
            RateLimiter::hit($key, 600);

            return $email !== null
                ? ApiResponse::error('Email or password is incorrect.', 401)
                : ApiResponse::error('Phone number or PIN is incorrect.', 401);
        }

        RateLimiter::clear($key);
        if ($user->hasRole(User::ROLE_STAFF_PENDING_REVIEW)) {
            return ApiResponse::error('Your staff access is under review. Contact an OpFin platform administrator.', 403);
        }
        $this->personalSpaces->ensure($user);
        $this->profiles->ensurePrimaryPhone($user);
        $token = $this->createAccessToken($user);

        return ApiResponse::success('Login successful', [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user),
            'credit_profile' => $this->profiles->status($user),
            'password_change_required' => (bool) $user->password_change_required,
        ]);
    }

    /** Signed-in staff replace their password, including a one-time password set by the owner. */
    public function changePassword(Request $request, AuditLogger $audit)
    {
        $user = $request->user();
        if (! StaffCredentialService::isStaff($user)) {
            return ApiResponse::error('Customers change their PIN in the OpFin app.', 403);
        }
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', $this->passwordRule()],
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }
        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            return ApiResponse::error('Your current password is incorrect.', 422, ['current_password' => ['Your current password is incorrect.']]);
        }
        if (Hash::check((string) $request->input('password'), $user->password)) {
            return ApiResponse::error('Choose a password different from your current one.', 422, ['password' => ['Choose a different password.']]);
        }

        $forced = (bool) $user->password_change_required;
        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'password_change_required' => false,
            'password_changed_at' => now(),
        ])->save();
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId !== null, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();
        $audit->record('auth.password_changed', $user, $user, ['forced' => $forced], $request);

        return ApiResponse::success('Your password has been changed. Other sessions have been signed out.', [
            'user' => $this->authUserPayload($user->fresh()),
            'password_change_required' => false,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|nullable|string',
            'email' => 'required_without:phone|nullable|email|max:254',
            'otp' => 'required|string|size:6',
            'verification_token' => 'nullable|string|size:64',
            'pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'pin_confirmation' => 'nullable|string|same:pin',
            'password' => 'nullable|string',
            'password_confirmation' => 'nullable|string|same:password',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        $email = $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null;
        if ($email !== null && ! $request->filled('password')) {
            return ApiResponse::error('Choose a new password.', 422, ['password' => ['Password is required.']]);
        }
        $credential = (string) ($request->input('pin') ?: $request->input('password'));
        if ($credential === '') {
            return ApiResponse::error('Create a new 6-digit PIN.', 422, ['pin' => ['PIN is required.']]);
        }
        if ($request->filled('pin') && $this->weakPin((string) $request->input('pin'))) {
            return ApiResponse::error('Choose a less predictable 6-digit PIN.', 422, ['pin' => ['Avoid repeated or sequential numbers.']]);
        }
        if ($request->filled('password')) {
            $legacyValidator = Validator::make($request->all(), [
                'password' => ['required', 'confirmed', $this->passwordRule()],
            ]);
            if ($legacyValidator->fails()) {
                return ApiResponse::error('Validation failed.', 422, $legacyValidator->errors()->toArray());
            }
        }

        if ($email !== null) {
            // An email code is bound to the staff account's phone record; unknown emails get the same answer as a wrong code.
            $user = $this->staffByEmail($email);
            if (! $user) {
                return ApiResponse::error('Invalid or expired OTP', 400);
            }
        } else {
            $user = User::where('phone', $request->phone)->first();
            if (! $user) {
                return ApiResponse::error('Invalid reset request', 404);
            }
        }

        $otpRecord = Otp::where('phone', $user->phone)->first();
        if ($email === null && app(CitoFeatureGate::class)->enabled('otp')) {
            try {
                app(CitoOtpAuthService::class)->verifyReset(
                    (string) $user->phone, (string) $request->otp,
                    $request->filled('verification_token') ? (string) $request->input('verification_token') : null,
                );
            } catch (\InvalidArgumentException $exception) {
                return ApiResponse::error('Invalid or expired OTP', 400);
            } catch (\Throwable $exception) {
                return ApiResponse::error('OTP verification temporarily unavailable.', 503);
            }
        } elseif (! $this->otpMatches($otpRecord, (string) $request->otp)) {
            return ApiResponse::error('Invalid or expired OTP', 400);
        }

        $user->password = Hash::make($credential);
        $user->password_change_required = false;
        $user->password_changed_at = now();
        if ($user->save()) {
            $otpRecord?->delete();
            $user->tokens()->delete();

            return ApiResponse::success('PIN has been reset successfully.');
        }

        return ApiResponse::error('Failed to reset PIN.', 500);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success('Logged out successfully.');
    }

    public function generateOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'nullable|in:sms,email',
            'phone' => 'required_unless:channel,email|nullable|string|max:32',
            'email' => 'required_if:channel,email|nullable|email|max:254',
            'app_signature' => 'nullable|string|max:32',
            'purpose' => 'nullable|in:LOGIN,REGISTRATION,PASSWORD_RESET',
            'otp_channel' => 'nullable|in:sms,whatsapp',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed.', 422, $validator->errors()->toArray());
        }

        if ($request->input('channel') !== 'email' && app(CitoFeatureGate::class)->enabled('otp')) {
            try {
                $result = app(CitoOtpAuthService::class)->start(
                    (string) $request->input('phone'), (string) $request->input('purpose', 'LOGIN'),
                    strtoupper((string) $request->input('otp_channel', 'sms'))
                );
                return ApiResponse::success('OTP challenge requested', [
                    'expires_at' => $result['expires_at'],
                    'max_attempts' => 3,
                ]);
            } catch (\InvalidArgumentException $exception) {
                return ApiResponse::error('Invalid OTP request.', 422);
            } catch (\Throwable $exception) {
                return ApiResponse::error('OTP service temporarily unavailable.', 503);
            }
        }

        if ($request->input('otp_channel') === 'whatsapp') {
            return ApiResponse::error('WhatsApp OTP requires an entitled Cito integration.', 503);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(5);

        if ($request->input('channel') === 'email') {
            return $this->emailResetCode(mb_strtolower(trim((string) $request->input('email'))), $otp, $expiresAt);
        }

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

        $signature = preg_replace('/\s+/', '', (string) $request->input('app_signature'));
        $message = 'OpFin: Your OTP code is '.$otp.'. It expires in 5 minutes.';
        if ($signature !== '') {
            $message .= "\n".$signature;
        }
        $this->smsService->queueSms($request->phone, $message, SmsService::redact($message, $otp));

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

        if (app(CitoFeatureGate::class)->enabled('otp')) {
            try {
                $token = app(CitoOtpAuthService::class)->verify(
                    (string) $request->input('phone'), (string) $request->input('otp')
                );
                return ApiResponse::success('OTP verified successfully', [
                    'verification_token' => $token,
                    'verification_expires_at' => now()->addMinutes(10)->toIso8601String(),
                ]);
            } catch (\InvalidArgumentException $exception) {
                return ApiResponse::error('Invalid or expired OTP', 400);
            } catch (\Throwable $exception) {
                return ApiResponse::error('OTP verification temporarily unavailable.', 503);
            }
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

    /**
     * Sends a reset code to a staff account's email. The answer is the same whether or not the
     * email belongs to a staff account, so the endpoint cannot be used to discover accounts.
     */
    private function emailResetCode(string $email, string $otp, Carbon $expiresAt)
    {
        $user = $this->staffByEmail($email);
        if ($user !== null && ! $user->hasRole(User::ROLE_STAFF_PENDING_REVIEW)) {
            Otp::updateOrCreate(['phone' => $user->phone], [
                'otp' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => $expiresAt,
                'verified_at' => null,
                'verification_token_hash' => null,
            ]);
            if (app()->environment('production') && in_array(config('mail.default'), ['log', 'array'], true)) {
                Log::warning('Password reset email requested but no mail provider is configured (MAIL_MAILER).');
            }
            Mail::to($user->email)->queue(new PasswordResetCode($otp, 5));
        }

        return ApiResponse::success('If an OpFin staff account uses this email, a code has been sent to it.', [
            'expires_at' => $expiresAt->toIso8601String(),
            'max_attempts' => 3,
        ]);
    }

    private function staffByEmail(string $email): ?User
    {
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        return $user !== null && StaffCredentialService::isStaff($user) ? $user : null;
    }

    private function weakPin(string $pin): bool
    {
        return preg_match('/^(\d)\1{5}$/', $pin) === 1
            || in_array($pin, ['012345', '123456', '234567', '345678', '456789', '987654', '876543', '765432', '654321', '543210'], true);
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

        return hash_equals($stored, $otp);
    }

    private function authUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'other_name' => $user->other_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'phone_verified_at' => $user->phone_verified_at,
            'email' => $user->email,
            'role' => $user->role,
            'password_change_required' => (bool) $user->password_change_required,
            'nin_status' => $user->nin_status,
            'national_id' => $user->national_id,
            'date_of_birth' => $user->date_of_birth,
            'preferred_language' => $user->preferred_language,
            'accessibility_preferences' => $user->accessibility_preferences,
        ];
    }
}
