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

    public function test_pin_deletion_purges_active_phone_and_wallet_context_but_retains_credit_reporting_evidence(): void
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
        $this->assertDatabaseHas('credit_profiles', ['user_id' => $user->id]);

        $deleted = User::withTrashed()->findOrFail($user->id);
        $this->assertNull($deleted->first_name);
        $this->assertNull($deleted->last_name);
        $this->assertNull($deleted->accessibility_preferences);
    }

    public function test_recorded_outstanding_obligation_blocks_deletion_until_resolved(): void
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

        $this->getJson('/api/account/deletion-readiness')
            ->assertOk()
            ->assertJsonPath('data.can_delete_account', false)
            ->assertJsonPath('data.active_obligations.0.code', 'personal_obligation')
            ->assertJsonPath('data.active_obligations.0.provider.name', 'Informal lender');

        $this->deleteJson('/api/account', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE',
        ])->assertStatus(409)
            ->assertJsonPath('data.deletion_status', 'blocked_obligations')
            ->assertJsonPath('data.active_obligations.0.code', 'personal_obligation');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);

        DB::table('financial_obligations')
            ->where('financial_space_id', $spaceId)
            ->update([
                'outstanding_amount_minor' => 0,
                'status' => 'settled',
                'updated_at' => now(),
            ]);

        $this->deleteJson('/api/account', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE',
        ])->assertOk()
            ->assertJsonPath('data.deletion_status', 'completed');

        $this->assertDatabaseHas('financial_obligations', [
            'financial_space_id' => $spaceId,
            'status' => 'settled',
        ]);
        $this->assertDatabaseHas('financial_assets', ['financial_space_id' => $spaceId]);
        $this->assertDatabaseHas('financial_spaces', ['id' => $spaceId]);
    }

    public function test_account_deletion_is_immediately_rejected_when_peer_finance_obligations_exist(): void
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
        ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.deletion_status', 'blocked_obligations')
            ->assertJsonPath('data.active_obligations.0.code', 'peer_borrowing')
            ->assertJsonPath('data.active_obligations.0.provider.name', 'Licensed Lender');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('support_cases', ['customer_id' => $user->id, 'category' => 'account_deletion']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'account.deletion.rejected_obligations', 'actor_id' => $user->id]);
    }

    public function test_customer_can_delete_selected_optional_data_without_deleting_account(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('DeleteMe!123'),
            'preferred_language' => 'lg',
            'accessibility_preferences' => ['large_text' => true],
        ]);
        DB::table('household_finance_profiles')->insert([
            'user_id' => $user->id,
            'household_size' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/account/data', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE_DATA',
            'data_categories' => ['household_and_microbusiness', 'profile_preferences'],
        ])->assertOk()
            ->assertJsonPath('data.deletion_status', 'data_deleted');

        $this->assertDatabaseMissing('household_finance_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);

        $fresh = User::findOrFail($user->id);
        $this->assertSame('en', $fresh->preferred_language);
        $this->assertNull($fresh->accessibility_preferences);
    }

    public function test_server_offline_sync_evidence_is_not_exposed_as_optional_deletion_data(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('DeleteMe!123'),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/account/deletion-readiness')
            ->assertOk()
            ->assertJsonMissing(['code' => 'offline_sync']);

        $this->deleteJson('/api/account/data', [
            'password' => 'DeleteMe!123',
            'confirmation' => 'DELETE_DATA',
            'data_categories' => ['offline_sync'],
        ])->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
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
        $this->assertSame(61, config('credit_distribution.defaults.play_store.personal_loan.min_duration_days'));
        $this->assertSame('review', config('credit_distribution.defaults.huawei_appgallery.*.availability'));
        $this->assertSame(36, config('credit_distribution.defaults.app_store.personal_loan.max_apr_percent'));
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('android'));
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('play_store'));
        $this->assertTrue(app(AppStoreCreditPolicy::class)->isStoreChannel('app_store'));
        $this->assertFalse(app(AppStoreCreditPolicy::class)->isStoreChannel('web'));
    }
}
