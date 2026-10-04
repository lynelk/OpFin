<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileHomeSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_home_aggregates_customer_state_and_supports_conditional_refresh(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/mobile/home')
            ->assertOk()
            ->assertJsonPath('data.availability.credit', true)
            ->assertJsonPath('data.availability.spaces', true)
            ->assertJsonPath('data.freshness.server_authoritative', true)
            ->assertJsonStructure([
                'data' => [
                    'compass',
                    'credit',
                    'policies',
                    'spaces',
                    'availability',
                    'freshness',
                ],
            ]);

        $this->assertLessThanOrEqual(
            100 * 1024,
            strlen((string) $first->getContent()),
            'The baseline mobile Home payload exceeds the 100 KB application-layer budget.'
        );

        $etag = (string) $first->headers->get('ETag');
        $this->assertNotSame('', $etag);

        $this->withHeader('If-None-Match', $etag)
            ->get('/api/mobile/home')
            ->assertStatus(304)
            ->assertHeader('ETag', $etag);
    }

    public function test_mobile_home_requires_authentication(): void
    {
        $this->getJson('/api/mobile/home')->assertUnauthorized();
    }
}
