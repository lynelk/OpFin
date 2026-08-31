<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\LongRangeFinancialActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LongRangeConceptCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_use_long_range_non_money_journeys_without_bypassing_verification(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100001']);
        Sanctum::actingAs($customer);

        $this->postJson('/api/long-range/linked-accounts', [
            'account_type' => 'mobile_money',
            'provider' => 'MTN',
            'identifier' => '256700100001',
        ])->assertCreated()->assertJsonPath('data.linked_account.status', 'pending_verification');

        $this->putJson('/api/long-range/household', [
            'household_size' => 4,
            'monthly_income_minor' => 1500000,
            'monthly_fixed_costs_minor' => 900000,
            'shared_goals' => [['name' => 'Emergency fund', 'target_minor' => 2000000]],
        ])->assertOk()->assertJsonPath('data.household.household_size', 4);

        $this->putJson('/api/long-range/microbusiness', [
            'business_name' => 'Kampala Test Shop',
            'business_type' => 'retail',
            'monthly_revenue_minor' => 3000000,
            'monthly_expense_minor' => 2100000,
        ])->assertOk()->assertJsonPath('data.microbusiness.business_type', 'retail');

        $this->postJson('/api/long-range/community-memberships', [
            'institution_type' => 'sacco',
            'institution_name' => 'Test SACCO',
            'member_reference' => 'MEM-101',
        ])->assertCreated()->assertJsonPath('data.membership.status', 'pending_verification');

        $this->postJson('/api/long-range/asset-finance', [
            'asset_category' => 'device',
            'asset_description' => 'Business smartphone',
            'asset_price_minor' => 800000,
            'deposit_minor' => 160000,
            'requested_term_months' => 6,
            'geolocation_consent' => false,
        ])->assertCreated()->assertJsonPath('data.asset_finance_request.status', 'submitted');

        $batchReference = (string) Str::uuid();
        $payload = [
            'batch_reference' => $batchReference,
            'device_reference' => 'device-1',
            'events' => [[
                'event_id' => 'offline-1',
                'occurred_at' => now()->toIso8601String(),
                'type' => 'budget.updated',
                'payload' => ['category' => 'food'],
            ]],
        ];
        $this->postJson('/api/long-range/offline-sync', $payload)->assertOk()->assertJsonPath('data.batch.status', 'processed');
        $this->postJson('/api/long-range/offline-sync', $payload)->assertOk()->assertJsonPath('data.batch.batch_reference', $batchReference);

        $this->postJson('/api/long-range/offline-sync', array_replace($payload, ['events' => [['event_id' => 'different']]]))->assertUnprocessable();

        $this->getJson('/api/long-range/overview')
            ->assertOk()
            ->assertJsonCount(1, 'data.linked_accounts')
            ->assertJsonCount(1, 'data.asset_finance')
            ->assertJsonCount(1, 'data.community_memberships');
    }

    public function test_ussd_is_read_or_redirect_only_for_sensitive_financial_actions(): void
    {
        User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100002']);

        $this->postJson('/api/channels/ussd', [
            'session_id' => 'ussd-session-1',
            'phone' => '256700100002',
            'text' => '',
        ])->assertOk()->assertJsonPath('data.continue', true);

        $this->postJson('/api/channels/ussd', [
            'session_id' => 'ussd-session-1',
            'phone' => '256700100002',
            'text' => '2',
        ])->assertOk()
            ->assertJsonPath('data.continue', false)
            ->assertJsonFragment(['message' => 'Borrowing requires secure authentication. Continue in OpFin or WhatsApp after verification.']);
    }

    public function test_participatory_finance_requires_independent_compliance_approval_and_step_up_before_cpay(): void
    {
        $borrower = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100003']);
        $investor = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100004']);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATIONS, 'phone' => '256700100005']);

        Sanctum::actingAs($borrower);
        $listingResponse = $this->postJson('/api/long-range/participatory/listings', [
            'purpose' => 'Working capital',
            'target_amount_minor' => 500000,
            'term_days' => 90,
            'lender_of_record' => 'Licensed Lender Ltd',
            'loss_allocation' => 'Investors bear disclosed pro-rata credit loss subject to lender-of-record terms.',
            'fees' => 'All fees disclosed before commitment.',
            'custody' => 'Funds remain on the governed CPay/provider rail until settlement.',
        ])->assertCreated();
        $listingId = $listingResponse->json('data.listing.id');

        $this->postJson("/api/admin/long-range/participatory/listings/{$listingId}/review", ['status' => 'approved'])->assertForbidden();

        Sanctum::actingAs($operator);
        $this->postJson("/api/admin/long-range/participatory/listings/{$listingId}/review", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('data.listing.status', 'funding');

        Sanctum::actingAs($investor);
        $commitmentResponse = $this->postJson('/api/long-range/participatory/commitments', [
            'listing_id' => $listingId,
            'amount_minor' => 100000,
        ])->assertCreated()->assertJsonPath('data.commitment.status', 'awaiting_step_up');
        $commitmentId = $commitmentResponse->json('data.commitment.id');

        $intentResponse = $this->postJson('/api/long-range/financial-intents', [
            'source_type' => 'participatory_commitment',
            'source_id' => $commitmentId,
            'amount_minor' => 100000,
            'idempotency_key' => 'p2p-intent-unique-0001',
        ])->assertCreated()->assertJsonPath('data.financial_intent.status', 'awaiting_step_up');
        $intentReference = $intentResponse->json('data.financial_intent.reference');

        $this->postJson("/api/long-range/financial-intents/{$intentReference}/confirm", [
            'verification_token' => str_repeat('a', 64),
        ])->assertUnprocessable();

        $this->assertDatabaseHas('participatory_finance_commitments', ['id' => $commitmentId, 'status' => 'awaiting_step_up']);
        $this->assertDatabaseCount('mobile_money_transactions', 0);
    }

    public function test_fresh_otp_step_up_can_submit_participatory_collection_to_cpay_without_fake_finality(): void
    {
        [$privateKey] = $this->keyPair();
        Config::set('services.cpay.base_url', 'https://cpay.example.test');
        Config::set('services.cpay.merchant_number', 'OPFIN-001');
        Config::set('services.cpay.private_key', $privateKey);
        Config::set('services.cpay.callback_url', 'https://opfin.example.test/api/webhooks/cpay');
        Config::set('services.cpay.country', 'UG');
        Config::set('services.cpay.environment', 'sandbox');
        Config::set('services.cpay.minor_unit_exponent', 0);
        Config::set('services.cpay.connect_retries', 0);
        Http::fake([
            'https://cpay.example.test/api/v2/native/payments/collect' => Http::response([
                'reference' => 'pending-reference',
                'transactionId' => 'cpay-p2p-1',
                'status' => 'PENDING',
                'currency' => 'UGX',
                'message' => 'Accepted',
            ], 202),
        ]);

        $borrower = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100006']);
        $investor = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '256700100007']);
        $listingId = DB::table('participatory_finance_listings')->insertGetId([
            'reference' => (string) Str::uuid(), 'borrower_user_id' => $borrower->id, 'purpose' => 'Stock',
            'target_amount_minor' => 200000, 'funded_amount_minor' => 0, 'term_days' => 30, 'status' => 'funding',
            'lender_of_record' => 'Licensed Lender Ltd', 'disclosures' => json_encode(['loss_allocation' => 'pro-rata', 'fees' => 'disclosed', 'custody' => 'CPay']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $commitmentId = DB::table('participatory_finance_commitments')->insertGetId([
            'reference' => (string) Str::uuid(), 'listing_id' => $listingId, 'investor_user_id' => $investor->id,
            'amount_minor' => 100000, 'status' => 'awaiting_step_up', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $verificationToken = bin2hex(random_bytes(32));
        Otp::create([
            'phone' => $investor->phone,
            'otp' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'verified_at' => now(),
            'verification_token_hash' => hash('sha256', $verificationToken),
        ]);

        $intent = app(LongRangeFinancialActionService::class)->createForSource($investor, 'participatory_commitment', $commitmentId, 100000, 'p2p-direct-test-0001');
        $confirmed = app(LongRangeFinancialActionService::class)->confirm($investor, $intent->reference, $verificationToken);

        $this->assertSame('provider_processing', $confirmed->status);
        $this->assertDatabaseHas('participatory_finance_commitments', ['id' => $commitmentId, 'status' => 'awaiting_step_up']);
        $this->assertDatabaseHas('mobile_money_transactions', ['user_id' => $investor->id, 'status' => 'pending', 'amount_minor' => 100000]);
        $this->assertSame(0, (int) DB::table('participatory_finance_listings')->where('id', $listingId)->value('funded_amount_minor'));
    }

    public function test_referral_rewards_are_maker_checker_and_ledger_backed(): void
    {
        $referrer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $referred = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $operator = User::factory()->create(['role' => User::ROLE_OPERATIONS]);

        Sanctum::actingAs($referrer);
        $response = $this->postJson('/api/long-range/referrals', [
            'referred_user_id' => $referred->id,
            'event_type' => 'eligible',
        ])->assertCreated();
        $referralId = $response->json('data.referral.id');

        Sanctum::actingAs($operator);
        $this->postJson("/api/admin/long-range/referrals/{$referralId}/reward", ['reward_minor' => 5000])
            ->assertOk()->assertJsonPath('data.referral.status', 'rewarded');

        $this->assertDatabaseHas('reward_ledger_entries', [
            'user_id' => $referrer->id,
            'referral_event_id' => $referralId,
            'direction' => 'credit',
            'amount_minor' => 5000,
            'status' => 'posted',
            'approved_by' => $operator->id,
        ]);
    }

    private function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($resource);
        $privateKey = '';
        $this->assertTrue(openssl_pkey_export($resource, $privateKey));
        $details = openssl_pkey_get_details($resource);
        $this->assertIsArray($details);

        return [$privateKey, $details['key']];
    }
}
