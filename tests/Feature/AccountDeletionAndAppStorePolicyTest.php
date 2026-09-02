<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AppStoreCreditPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionAndAppStorePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_delete_account_in_app_after_reauthentication(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('DeleteMe!123'),
            'national_id' => 'CM123456789012',
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE',
        ])->assertOk()
            ->assertJsonPath('data.deletion_status', 'completed');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id, 'national_id' => 'CM123456789012']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account.deletion.completed', 'actor_id' => $user->id]);
    }

    public function test_deletion_request_stays_open_when_peer_finance_obligations_exist(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('DeleteMe!123'),
        ]);
        DB::table('participatory_finance_listings')->insert([
            'reference' => (string) Str::uuid(),
            'borrower_user_id' => $user->id,
            'purpose' => 'Working capital',
            'target_amount_minor' => 500000,
            'funded_amount_minor' => 0,
            'term_days' => 90,
            'status' => 'funding',
            'lender_of_record' => 'Licensed Lender',
            'disclosures' => json_encode(['fees' => 'disclosed', 'loss_allocation' => 'disclosed', 'custody' => 'disclosed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE',
        ])->assertStatus(202)
            ->assertJsonPath('data.deletion_status', 'pending_obligations');

        $this->assertDatabaseMissing('users', ['id' => $user->id, 'deleted_at' => now()]);
        $this->assertDatabaseHas('support_cases', ['customer_id' => $user->id, 'category' => 'account_deletion']);
    }

    public function test_wrong_password_cannot_delete_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Correct!123')]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', [
            'password' => 'Wrong!123',
            'confirmation' => 'DELETE',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_app_store_policy_rejects_short_personal_loan_terms(): void
    {
        $this->assertSame(61, AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS);
        $this->assertSame(36.0, AppStoreCreditPolicy::MAX_APR_PERCENT);
    }
}
