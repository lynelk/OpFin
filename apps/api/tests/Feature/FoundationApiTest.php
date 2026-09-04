<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FoundationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_standard_response_and_sanctum_token(): void
    {
        User::factory()->create([
            'phone' => '256700000001',
            'password' => Hash::make('correct-password'),
            'role' => 'customer',
        ]);

        $this->postJson('/api/login', [
            'phone' => '256700000001',
            'password' => 'correct-password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonPath('data.user.phone', '256700000001')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['access_token', 'token_type', 'user'],
            ]);
    }

    public function test_authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'phone' => '256700000002',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.permissions.0', 'profile.view');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertStatus(401);
    }

    public function test_role_check_blocks_users_without_required_role(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/admin/foundation-check')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_platform_admin_role_can_access_admin_foundation_check(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'platform_admin']));

        $this->getJson('/api/admin/foundation-check')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'platform_admin');
    }

    public function test_operations_role_can_access_admin_foundation_check(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'operations']));

        $this->getJson('/api/admin/foundation-check')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'operations');
    }

    public function test_foundation_roles_and_permissions_are_defined(): void
    {
        $this->assertSame([
            'platform_admin',
            'operations',
            'customer',
            'employer_admin',
            'support',
        ], User::ROLES);

        $this->assertContains('*', User::ROLE_PERMISSIONS['platform_admin']);
        $this->assertContains('operations.view', User::ROLE_PERMISSIONS['operations']);
        $this->assertContains('profile.view', User::ROLE_PERMISSIONS['customer']);
        $this->assertContains('employer.view', User::ROLE_PERMISSIONS['employer_admin']);
        $this->assertContains('support.view', User::ROLE_PERMISSIONS['support']);
    }

    public function test_sensitive_profile_access_creates_audit_log(): void
    {
        $user = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'profile.viewed',
            'actor_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('event', 'profile.viewed')->firstOrFail();
        $this->assertSame('GET', $auditLog->metadata['method']);
        $this->assertSame('/api/profile', $auditLog->metadata['path']);
    }

    public function test_health_endpoint_returns_standard_response(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }
}
