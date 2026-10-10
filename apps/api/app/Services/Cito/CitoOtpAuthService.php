<?php

namespace App\Services\Cito;

use App\Models\Otp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CitoOtpAuthService
{
    public function start(string $phone, string $purpose, string $channel = 'SMS'): array
    {
        app(CitoFeatureGate::class)->requireEnabled('otp');
        $purpose = strtoupper(trim($purpose));
        $channel = strtoupper(trim($channel));
        if (! in_array($channel, ['SMS', 'WHATSAPP'], true)) {
            throw new InvalidArgumentException('Unsupported OTP delivery channel.');
        }
        if ($channel === 'WHATSAPP') {
            app(CitoFeatureGate::class)->requireEnabled('otp_whatsapp');
        }
        if (! in_array($purpose, ['LOGIN', 'REGISTRATION', 'PASSWORD_RESET'], true)) {
            throw new InvalidArgumentException('Unsupported OTP purpose.');
        }
        $recipient = $this->e164($phone);
        $env = strtoupper((string) config('services.cito.environment', 'SANDBOX'));
        $lock = Cache::lock('opfin:cito:otp:'.hash('sha256', $phone), 30);
        if (! $lock->get()) {
            throw new RuntimeException('An OTP operation is in progress.');
        }
        try {
            $row = DB::table('cito_otp_challenges')->where('phone', $phone)->first();
            if ($row && $row->purpose === $purpose && $row->channel === $channel && $row->environment === $env
                && $row->consumed_at === null && $row->verified_at === null && Carbon::parse($row->expires_at)->isFuture()
                && $row->challenge_reference !== null) {
                return ['expires_at' => Carbon::parse($row->expires_at)->toIso8601String()];
            }
            $key = $row && $row->purpose === $purpose && $row->channel === $channel && $row->environment === $env
                && $row->challenge_reference === null && Carbon::parse($row->expires_at)->isFuture()
                ? $row->idempotency_key : (string) Str::uuid();

            DB::table('cito_otp_challenges')->updateOrInsert(['phone' => $phone], [
                'purpose' => $purpose, 'channel' => $channel, 'environment' => $env, 'idempotency_key' => $key,
                'challenge_reference' => null, 'status' => 'created',
                'expires_at' => now()->addMinutes(5), 'verified_at' => null,
                'consumed_at' => null, 'attempts' => 0,
                'updated_at' => now(), 'created_at' => now(),
            ]);

            $result = app(CitoCommunicationsClient::class)->createOtpChallenge($recipient, $purpose, 'en-UG', $key, $channel);
            $challenge = $result['challengeId'] ?? null;
            $expires = isset($result['expiresAt']) ? Carbon::parse($result['expiresAt']) : null;
            if (! is_string($challenge) || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $challenge)
                || $expires === null || ! $expires->isFuture()
                || strtoupper((string) ($result['environment'] ?? '')) !== $env
                || strtoupper((string) ($result['status'] ?? '')) !== 'PENDING') {
                throw new RuntimeException('Cito OTP response requires operational review.');
            }
            $expires = $expires->min(now()->addMinutes(5));
            DB::transaction(function () use ($phone, $challenge, $expires) {
                DB::table('cito_otp_challenges')->where('phone', $phone)->update([
                    'challenge_reference' => $challenge, 'status' => 'pending',
                    'expires_at' => $expires, 'updated_at' => now(),
                ]);
                // A random sentinel replaces local code verification. Never persist or log
                // the remote OTP value in OpFin.
                Otp::updateOrCreate(['phone' => $phone], [
                    'otp' => Hash::make(bin2hex(random_bytes(32))),
                    'attempts' => 0, 'expires_at' => $expires,
                    'verified_at' => null, 'verification_token_hash' => null,
                ]);
            });

            return ['expires_at' => $expires->toIso8601String()];
        } finally {
            $lock->release();
        }
    }

    public function verify(string $phone, string $code): string
    {
        app(CitoFeatureGate::class)->requireEnabled('otp');
        $result = $this->checkAndConsume($phone, $code, false);

        return $result;
    }

    public function verifyReset(string $phone, string $code, ?string $verificationToken = null): void
    {
        app(CitoFeatureGate::class)->requireEnabled('otp');
        $row = DB::table('cito_otp_challenges')->where('phone', $phone)->first();
        if (! $row || $row->purpose !== 'PASSWORD_RESET') {
            throw new InvalidArgumentException('An active password-reset OTP is required.');
        }
        if ($row->verified_at !== null && $row->consumed_at === null) {
            $otp = Otp::where('phone', $phone)->first();
            if ($verificationToken === null || ! $otp || ! $otp->verified_at
                || $otp->verified_at->lt(now()->subMinutes(10))
                || ! $otp->verification_token_hash
                || ! hash_equals((string) $otp->verification_token_hash, hash('sha256', $verificationToken))) {
                throw new InvalidArgumentException('Verified reset requires its bound verification token.');
            }
            $changed = DB::table('cito_otp_challenges')->where('phone', $phone)
                ->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);
            if ($changed !== 1) {
                throw new InvalidArgumentException('OTP already consumed.');
            }

            return;
        }
        $this->checkAndConsume($phone, $code, true);
    }

    private function checkAndConsume(string $phone, string $code, bool $reset): string
    {
        $lock = Cache::lock('opfin:cito:otp:'.hash('sha256', $phone), 30);
        if (! $lock->get()) {
            throw new RuntimeException('An OTP operation is in progress.');
        }
        try {
            $row = DB::table('cito_otp_challenges')->where('phone', $phone)->first();
            if (! $row || $row->consumed_at !== null || $row->verified_at !== null
                || $row->challenge_reference === null || Carbon::parse($row->expires_at)->isPast()
                || (int) $row->attempts >= 3
                || $row->environment !== strtoupper((string) config('services.cito.environment', 'SANDBOX'))
                || ($reset && $row->purpose !== 'PASSWORD_RESET')) {
                throw new InvalidArgumentException('Invalid or expired OTP.');
            }
            $response = app(CitoCommunicationsClient::class)->verifyOtpChallenge($row->challenge_reference, $code);
            if (strtoupper((string) ($response['status'] ?? '')) !== 'VERIFIED') {
                DB::table('cito_otp_challenges')->where('phone', $phone)->update([
                    'attempts' => (int) $row->attempts + 1,
                    'status' => strtolower((string) ($response['status'] ?? 'invalid')),
                    'updated_at' => now(),
                ]);
                throw new InvalidArgumentException('Invalid or expired OTP.');
            }
            $token = bin2hex(random_bytes(32));
            DB::transaction(function () use ($phone, $token, $reset) {
                $updated = DB::table('cito_otp_challenges')->where('phone', $phone)
                    ->whereNull('verified_at')->whereNull('consumed_at')->update([
                        'verified_at' => now(), 'status' => 'verified',
                        'consumed_at' => $reset ? now() : null, 'updated_at' => now(),
                    ]);
                if ($updated !== 1) {
                    throw new InvalidArgumentException('OTP already verified.');
                }
                if (! $reset) {
                    $saved = Otp::where('phone', $phone)->update([
                        'verified_at' => now(), 'verification_token_hash' => hash('sha256', $token),
                    ]);
                    if ($saved !== 1) {
                        throw new RuntimeException('OTP verification record was not available.');
                    }
                }
            });

            return $token;
        } finally {
            $lock->release();
        }
    }

    private function e164(string $phone): string
    {
        $p = preg_replace('/\\s+/', '', $phone);
        if (preg_match('/^256[0-9]{9}$/', $p)) {
            $p = '+'.$p;
        } elseif (preg_match('/^0[0-9]{9}$/', $p)) {
            $p = '+256'.substr($p, 1);
        }
        if (! preg_match('/^\\+[1-9][0-9]{7,14}$/', $p)) {
            throw new InvalidArgumentException('Invalid OTP phone format.');
        }

        return $p;
    }
}
