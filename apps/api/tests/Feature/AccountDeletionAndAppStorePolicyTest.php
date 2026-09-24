<?php

namespace Tests\Feature;

use App\Models\CreditProfile;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
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

    public function test_pin_deletion_purges_active_phone_wallet_and_credit_profile_context(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone' => '256700222111',
            'phone_verified_at' => now(),
            'password' => Hash::make('482951'),
            'first_name' => 'Amina',
            'last_name' => 'Kato',
            'accessibility_preferences' => ['large_text' => true],
        ]);

        $phone = CustomerPhoneNumber::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'kind' => 'primary',
            'verified_at' => now(),
        ]);

        CustomerWallet::create([
            'user_id' => $user->id,
            'phone_number_id' => $phone->id,
            'provider' => 'mobile_money',
            'msisdn' => $user->phone,
            'status' => 'active',
            'is_default_disbursement' => true,
            'is_default_repayment' => true,
            'verified_at' => now(),
        ]);

        CreditProfile::create([
            'user_id' => $user->id,
            'status' => CreditProfile::STATUS_PENDING,
            'coverage_percent' => 0,
            'credit_limit_minor' => 0,
            'current_exposure_minor' => 0,
            'available_to_borrow_minor' => 0,
            'amount_due_minor' => 0,
            'total_outstanding_minor' => 0,
            'model_version' => 'test',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/account', [
            'pin' => '482951',
            'confirmation' => 'DELETE',
        ])->assertOk()
            ->assertJsonPath('data.deletion_status', 'completed');

        $this->assertDatabaseMissing('customer_phone_numbers', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('customer_wallets', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('credit_profiles', ['user_id' => $user->id]);

        $deleted = User::withTrashed()->findOrFail($user->id);
        $this->assertNull($deleted->first_name);
        $this->assertNull($deleted->last_name);
        $this->assertNull($deleted->accessibility_preferences);
    }

    public function test_account_deletion_purges_personal_space_planning_records(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('DeleteMe!123'),
        ]);
        Sanctum::actingAs($user);

        $spaceId = (int) $this->getJson('/api/financial-spaces')
            ->assertOk()
            ->json('data.spaces.0.id');

        $this->postJson('/api/financial-spaces/'.$spaceId.'/obligations', [
            'kind' => 'personal_debt',
            'direction' => 'i_owe',
            'counterparty_name' => 'Informal lender',
            'amount_minor' => 80000,
        ])->assertCreated();

        $this->postJson('/api/financial-spaces/'.$spaceId.'/assets', [
            'asset_type' => 'household',
            'name' => 'Personal planning asset',
            'value_minor' => 250000,
        ])->assertCreated();

        $this->deleteJson('/api/account', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE',
        ])->assertOk()
            ->assertJsonPath('data.deletion_status', 'completed');

        $this->assertDatabaseMissing('financial_obligations', [
            'financial_space_id' => $spaceId,
        ]);
        $this->assertDatabaseMissing('financial_assets', [
            'financial_space_id' => $spaceId,
        ]);
        $this->assertDatabaseMissing('financial_space_memberships', [
            'financial_space_id' => $spaceId,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('financial_spaces', [
            'id' => $spaceId,
        ]);
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

    public function test_mobile_store_policy_defines_safe_personal_loan_boundaries(): void
    {
        $this->assertSame(61, AppStoreCreditPolicy::MIN_FULL_REPAYMENT_DAYS);
        $this->assertSame(90, AppStoreCreditPolicy::PREFERRED_FULL_REPAYMENT_DAYS);
        $this->assertSame(36.0, AppStoreCreditPolicy::MAX_APR_PERCENT);
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('android'));
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('play_store'));
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('app_store'));
        $this->assertFalse(app(AppStoreCreditPolicy::class)->isStoreChannel('web'));
    }
}
