<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpFinPeopleMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_and_lookup_require_authentication(): void
    {
        $this->getJson('/api/people/business-spaces')->assertUnauthorized();
        $this->getJson('/api/people/business-spaces/any')->assertUnauthorized();
    }

    public function test_only_current_users_active_business_memberships_are_visible(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->space('business', 'active');
        $inactive = $this->space('business', 'inactive');
        $personal = $this->space('personal', 'active');
        $foreign = $this->space('business', 'active');
        $this->membership($mine, $user->id, 'active', 'owner');
        $this->membership($inactive, $user->id, 'active', 'owner');
        $this->membership($personal, $user->id, 'active', 'owner');
        $this->membership($foreign, $other->id, 'active', 'owner');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/people/business-spaces')->assertOk();
        $response->assertJsonCount(1, 'data.spaces');
        $response->assertJsonPath('data.spaces.0.public_id', $mine);
        $this->getJson('/api/people/business-spaces/'.$foreign)->assertNotFound();
        $this->getJson('/api/people/business-spaces/'.$inactive)->assertNotFound();
        $this->getJson('/api/people/business-spaces/'.$personal)->assertNotFound();
    }

    public function test_management_permission_is_not_granted_to_ordinary_member(): void
    {
        $user = User::factory()->create();
        $space = $this->space('business', 'active');
        $this->membership($space, $user->id, 'active', 'member');
        Sanctum::actingAs($user);
        $this->getJson('/api/people/business-spaces/'.$space)
            ->assertOk()->assertJsonPath('data.can_manage_people', false);
    }

    private function space(string $type, string $status): string
    {
        $publicId = (string) Str::uuid();
        DB::table('financial_spaces')->insert([
            'public_id' => $publicId, 'type' => $type, 'name' => 'Test organisation',
            'country' => 'UG', 'currency' => 'UGX', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $publicId;
    }

    private function membership(string $publicId, int $userId, string $status, string $role): void
    {
        $id = DB::table('financial_spaces')->where('public_id', $publicId)->value('id');

        DB::table('financial_space_memberships')->insert([
            'financial_space_id' => $id, 'user_id' => $userId,
            'role' => $role, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
