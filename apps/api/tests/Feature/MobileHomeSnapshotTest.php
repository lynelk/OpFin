<?php

namespace Tests\Feature;

use App\Models\ProtectionPolicy;
use App\Models\ProtectionProduct;
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

    public function test_mobile_home_returns_only_bounded_current_protection_summaries(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $product = ProtectionProduct::create([
            'code' => 'HOME-COVER',
            'name' => 'Home Snapshot Cover',
            'insurer_name' => 'Example Insurer',
            'country_code' => 'UG',
            'currency' => 'UGX',
            'product_type' => 'funeral',
            'audience_scope' => 'personal',
            'status' => ProtectionProduct::STATUS_ACTIVE,
            'premium_amount_minor' => 1000,
            'premium_frequency' => 'monthly',
            'coverage_limit_minor' => 100000,
            'disclosure_version' => 'v1',
            'disclosure_payload' => [],
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        for ($index = 0; $index < 8; $index++) {
            ProtectionPolicy::create([
                'protection_product_id' => $product->id,
                'user_id' => $user->id,
                'coverage_scope' => 'personal',
                'policy_reference' => 'HOME-POLICY-'.$index,
                'status' => ProtectionPolicy::STATUS_ACTIVE,
                'premium_amount_minor' => 1000,
                'premium_frequency' => 'monthly',
                'coverage_limit_minor' => 100000,
                'disclosure_hash' => str_repeat('a', 64),
                'acceptance_metadata' => [
                    'raw_evidence' => str_repeat('x', 20000),
                ],
                'enrolled_at' => now()->subDays($index),
            ]);
        }

        ProtectionPolicy::create([
            'protection_product_id' => $product->id,
            'user_id' => $user->id,
            'coverage_scope' => 'personal',
            'policy_reference' => 'HOME-CANCELLED',
            'status' => ProtectionPolicy::STATUS_CANCELLED,
            'premium_amount_minor' => 1000,
            'premium_frequency' => 'monthly',
            'coverage_limit_minor' => 100000,
            'disclosure_hash' => str_repeat('b', 64),
            'acceptance_metadata' => [
                'raw_evidence' => str_repeat('y', 20000),
            ],
            'enrolled_at' => now()->subDays(20),
            'cancelled_at' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/mobile/home')
            ->assertOk()
            ->assertJsonCount(5, 'data.policies')
            ->assertJsonMissingPath('data.policies.0.acceptance_metadata')
            ->assertJsonMissingPath('data.policies.0.premium_payments')
            ->assertJsonMissingPath('data.policies.0.claims');

        $this->assertLessThanOrEqual(
            100 * 1024,
            strlen((string) $response->getContent()),
            'The populated mobile Home payload exceeds the 100 KB application-layer budget.'
        );
    }

    public function test_mobile_home_requires_authentication(): void
    {
        $this->getJson('/api/mobile/home')->assertUnauthorized();
    }
}
