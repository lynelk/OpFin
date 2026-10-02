<?php

namespace Tests\Feature;

use App\Models\CreditProfile;
use App\Models\CustomerPhoneNumber;
use App\Models\CustomerWallet;
use App\Models\ProtectionClaim;
use App\Models\ProtectionPolicy;
use App\Models\ProtectionPremiumPayment;
use App\Models\ProtectionProduct;
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

    public function test_selective_location_deletion_keeps_other_customers_and_regulated_asset_context(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $records = [];
        foreach ([[$owner->id, 'user', $owner->id, 'personal_service_discovery'], [$other->id, 'user', $other->id, 'personal_service_discovery'], [$owner->id, 'asset', 500, 'asset_verification']] as [$userId, $subjectType, $subjectId, $purpose]) {
            $records[] = DB::table('location_contexts')->insertGetId([
                'public_id' => (string) Str::uuid(), 'user_id' => $userId,
                'subject_type' => $subjectType, 'subject_id' => $subjectId, 'purpose' => $purpose,
                'source' => 'manual', 'consent_purpose' => $purpose, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        Sanctum::actingAs($owner);
        $this->deleteJson('/api/account/data', ['pin' => '482951', 'confirmation' => 'DELETE_DATA', 'data_categories' => ['location_context']])->assertOk();
        $this->assertDatabaseMissing('location_contexts', ['id' => $records[0]]);
        $this->assertDatabaseHas('location_contexts', ['id' => $records[1]]);
        $this->assertDatabaseHas('location_contexts', ['id' => $records[2]]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'deleted_at' => null]);
    }

    public function test_legacy_web_deletion_uses_the_same_obligation_check_and_requires_reauthentication(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        DB::table('participatory_finance_listings')->insert([
            'reference' => (string) Str::uuid(), 'borrower_user_id' => $user->id, 'purpose' => 'Test obligation',
            'target_amount_minor' => 500000, 'funded_amount_minor' => 500000, 'term_days' => 90,
            'status' => 'funded', 'lender_of_record' => 'Recorded lender', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->from('/account/delete')->delete('/account/delete', ['phone' => $user->phone, 'pin' => '482951', 'confirmation' => 'DELETE'])
            ->assertRedirect('/account/delete')->assertSessionHas('deletion_blockers');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
        $this->from('/account/delete')->delete('/account/delete', ['phone' => $user->phone, 'pin' => '000000', 'confirmation' => 'DELETE'])
            ->assertRedirect('/account/delete')->assertSessionHas('error');
    }

    public function test_nonterminal_protection_processing_blocks_deletion_even_after_policy_cancellation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => '482951']);
        $product = ProtectionProduct::create([
            'code' => 'DELETION-PREMIUM', 'name' => 'Test protection', 'insurer_name' => 'Recorded insurer',
            'country_code' => 'UG', 'currency' => 'UGX', 'product_type' => 'funeral', 'status' => 'draft',
            'premium_amount_minor' => 1000, 'premium_frequency' => 'monthly', 'disclosure_payload' => [],
        ]);
        $policy = ProtectionPolicy::create([
            'protection_product_id' => $product->id, 'user_id' => $user->id, 'policy_reference' => 'POLICY-PENDING',
            'status' => 'premium_pending', 'premium_amount_minor' => 1000, 'premium_frequency' => 'monthly',
            'disclosure_hash' => str_repeat('a', 64), 'enrolled_at' => now(),
        ]);
        Sanctum::actingAs($user);
        foreach (['premium_pending', 'pending_issuance'] as $status) {
            $policy->update(['status' => $status]);
            $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', false);
        }
        $policy->update(['status' => 'cancelled']);
        $payment = ProtectionPremiumPayment::create([
            'protection_policy_id' => $policy->id, 'user_id' => $user->id, 'payment_reference' => 'PREMIUM-UNSETTLED',
            'idempotency_key' => 'premium-deletion-boundary', 'status' => 'collected_pending_partner',
            'amount_minor' => 1000, 'currency' => 'UGX', 'requested_at' => now(),
        ]);
        foreach (['collection_pending', 'collected_pending_partner', 'reversal_exception'] as $status) {
            $payment->update(['status' => $status]);
            $this->deleteJson('/api/account', ['pin' => '482951', 'confirmation' => 'DELETE'])->assertStatus(409)
                ->assertJsonPath('data.active_obligations.0.code', 'protection_premium_pending');
        }
        $payment->update(['status' => 'reversed']);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', true);
        ProtectionClaim::create([
            'protection_policy_id' => $policy->id, 'user_id' => $user->id, 'claim_reference' => 'DECLINED-CLAIM',
            'status' => 'declined', 'incident_date' => now()->toDateString(), 'category' => 'test',
            'description' => 'Resolved test claim', 'submitted_at' => now(), 'resolved_at' => now(),
        ]);
        $this->getJson('/api/account/deletion-readiness')->assertOk()->assertJsonPath('data.can_delete_account', true);
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
