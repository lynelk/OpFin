<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpFinPeopleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_verification_fails_closed_when_not_configured(): void
    {
        config()->set('services.opfin_people.verification_secret', null);
        $this->postJson('/api/people/service/verify-membership', [
            'user_id' => 1, 'space_public_id' => '00000000-0000-4000-8000-000000000001',
        ])->assertStatus(503);
    }

    public function test_service_verification_rejects_missing_signature(): void
    {
        config()->set('services.opfin_people.verification_secret', str_repeat('a', 48));
        $this->postJson('/api/people/service/verify-membership', [
            'user_id' => 1, 'space_public_id' => '00000000-0000-4000-8000-000000000001',
        ])->assertUnauthorized();
    }

    public function test_service_verification_rejects_replayed_request(): void
    {
        config()->set('services.opfin_people.verification_secret', str_repeat('b', 48));
        Cache::flush();
        $body = json_encode(['user_id' => 999, 'space_public_id' => '00000000-0000-4000-8000-000000000001']);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $timestamp."\n".$nonce."\n".$body, str_repeat('b', 48));
        $headers = [
            'Content-Type' => 'application/json',
            'X-OpFin-People-Timestamp' => $timestamp,
            'X-OpFin-People-Nonce' => $nonce,
            'X-OpFin-People-Signature' => $signature,
        ];
        $this->call('POST', '/api/people/service/verify-membership', [], [], [], $this->serverHeaders($headers), $body)
            ->assertOk()->assertJsonPath('data.verified', false);
        $this->call('POST', '/api/people/service/verify-membership', [], [], [], $this->serverHeaders($headers), $body)
            ->assertUnauthorized();
    }

    private function serverHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result[strtoupper(str_replace('-', '_', $key)) === 'CONTENT_TYPE' ? 'CONTENT_TYPE' : 'HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        return $result;
    }
}
