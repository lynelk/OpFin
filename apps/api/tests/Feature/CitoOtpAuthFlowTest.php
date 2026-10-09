<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CitoOtpAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function enableCito(): void
    {
        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        Config::set('services.cito.base_url', 'https://cito.example.test');
        Config::set('services.cito.merchant_number', 'OPFIN-1');
        Config::set('services.cito.private_key', $private);
        Config::set('services.cito.environment', 'SANDBOX');
        Config::set('services.cito.feature_flags.otp', true);

        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/verify')) {
                return Http::response(['challengeId' => 'OTP-test', 'status' => 'VERIFIED'], 200);
            }
            return Http::response([
                'challengeId' => 'OTP-test',
                'status' => 'PENDING',
                'environment' => 'SANDBOX',
                'expiresAt' => now()->addMinutes(5)->toIso8601String(),
            ], 201);
        });
    }

    public function test_cito_otp_uses_external_challenge_without_local_code_storage_or_reverification(): void
    {
        $this->enableCito();
        $this->postJson('/api/generate-otp', [
            'phone' => '256700000001', 'purpose' => 'REGISTRATION',
        ])->assertOk();
        $this->assertDatabaseHas('cito_otp_challenges', [
            'phone' => '256700000001', 'challenge_reference' => 'OTP-test',
        ]);
        $this->assertNotNull(Otp::where('phone', '256700000001')->first());
        $verified = $this->postJson('/api/verify-otp', [
            'phone' => '256700000001', 'otp' => '123456',
        ])->assertOk();
        $this->assertSame(64, strlen((string) $verified->json('data.verification_token')));
        $this->postJson('/api/verify-otp', [
            'phone' => '256700000001', 'otp' => '654321',
        ])->assertStatus(400);
    }

    public function test_pin_reset_consumes_provider_challenge_once(): void
    {
        $this->enableCito();
        $user = User::factory()->create(['phone' => '256700000002']);
        $this->postJson('/api/generate-otp', [
            'phone' => $user->phone, 'purpose' => 'PASSWORD_RESET',
        ])->assertOk();
        $payload = [
            'phone' => $user->phone, 'otp' => '123456',
            'pin' => '826419', 'pin_confirmation' => '826419',
        ];
        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('826419', $user->fresh()->password));
        $this->postJson('/api/reset-password', $payload)->assertStatus(400);
    }

    public function test_provider_outage_does_not_generate_an_untracked_fallback_otp(): void
    {
        $this->enableCito();
        Http::fake(['https://cito.example.test/*' => Http::response(['code' => 'PROVIDER_DOWN'], 503)]);
        $this->postJson('/api/generate-otp', [
            'phone' => '256700000003', 'purpose' => 'REGISTRATION',
        ])->assertStatus(503);
        $this->assertDatabaseMissing('otps', ['phone' => '256700000003']);
    }
}
