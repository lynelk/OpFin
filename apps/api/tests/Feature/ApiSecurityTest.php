<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_requires_valid_otp(): void
    {
        $user = User::factory()->create([
            'phone' => '256700000001',
            'password' => Hash::make('old-password'),
        ]);

        $this->postJson('/api/reset-password', [
            'phone' => $user->phone,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_reset_consumes_otp_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create([
            'phone' => '256700000002',
            'password' => Hash::make('old-password'),
        ]);
        $user->createToken('auth_token');

        Otp::create([
            'phone' => $user->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/reset-password', [
            'phone' => $user->phone,
            'otp' => '123456',
            'password' => 'New-password-123!',
            'password_confirmation' => 'New-password-123!',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('New-password-123!', $user->fresh()->password));
        $this->assertDatabaseMissing('otps', ['phone' => $user->phone]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_reset_rejects_weak_passwords(): void
    {
        $user = User::factory()->create(['phone' => '256700000003']);

        Otp::create([
            'phone' => $user->phone,
            'otp' => '123456',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/reset-password', [
            'phone' => $user->phone,
            'otp' => '123456',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_cannot_read_another_users_loan_applications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/loan-applications/{$otherUser->id}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_non_admin_cannot_approve_transactions(): void
    {
        $user = User::factory()->create(['role' => 'Member']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/transactions/1/approve')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_direct_provider_callback_route_is_not_exposed(): void
    {
        $this->postJson('/api/mtn-callback', [
            'externalId' => 'test-reference',
            'status' => 'SUCCESSFUL',
        ])->assertNotFound();
    }
}
