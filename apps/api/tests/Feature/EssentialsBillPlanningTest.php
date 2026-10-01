<?php

namespace Tests\Feature;

use App\Models\CustomerWallet;
use App\Models\EssentialsAccount;
use App\Models\EssentialsBiller;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\CpayEssentialsClient;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EssentialsBillPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recurring_bill_planning_keeps_future_obligations_in_the_affordability_horizon(): void
    {
        Carbon::setTestNow('2026-09-28 08:00:00');
        [$user, $spaceId, $account] = $this->customerContext();

        DB::table('financial_accounts')->insert([
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'display_name' => 'Recorded cash',
            'account_type' => 'cash',
            'balance_minor' => 300000,
            'currency' => 'UGX',
            'confidence' => 'user_reported',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('financial_calendar_events')->insert([
            [
                'user_id' => $user->id,
                'financial_space_id' => $spaceId,
                'title' => 'School transport',
                'event_type' => 'bill',
                'direction' => 'expense',
                'amount_minor' => 25000,
                'currency' => 'UGX',
                'scheduled_for' => '2026-10-15 08:00:00',
                'certainty' => 'scheduled',
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'financial_space_id' => $spaceId,
                'title' => 'Confirmed income',
                'event_type' => 'income',
                'direction' => 'income',
                'amount_minor' => 40000,
                'currency' => 'UGX',
                'scheduled_for' => '2026-10-20 08:00:00',
                'certainty' => 'scheduled',
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Sanctum::actingAs($user);

        $otherPlan = $this->postJson('/api/essentials/bill-plans', [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'expected_amount_minor' => 50000,
            'currency' => 'UGX',
            'frequency' => 'monthly',
            'next_due_date' => '2026-10-05',
            'necessary' => true,
        ])->assertCreated()->json('data.plan');

        $targetPlan = $this->postJson('/api/essentials/bill-plans', [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'expected_amount_minor' => 100000,
            'currency' => 'UGX',
            'frequency' => 'monthly',
            'next_due_date' => '2026-10-01',
            'necessary' => true,
        ])->assertCreated()->json('data.plan');

        $this->assertNotSame($otherPlan['id'], $targetPlan['id']);

        $assessment = $this->postJson('/api/essentials/affordability', [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'bill_plan_id' => $targetPlan['id'],
            'bill_amount_minor' => 100000,
            'bill_due_date' => '2026-10-01',
            'horizon_end' => '2026-11-10',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.assessment');

        $this->assertSame(300000, (int) $assessment['recorded_available_minor']);
        $this->assertSame(40000, (int) $assessment['projected_income_minor']);
        $this->assertSame(225000, (int) $assessment['scheduled_outflows_minor']);
        $this->assertSame(75000, (int) $assessment['own_money_capacity_minor']);
        $this->assertSame(25000, (int) $assessment['financing_gap_minor']);
        $this->assertSame(15000, (int) $assessment['minimum_projected_balance_minor']);
        $this->assertSame('partial_gap', $assessment['classification']);
        $this->assertTrue((bool) $assessment['snapshot']['recorded_data_only']);
    }

    public function test_own_money_bill_payment_requires_confirmed_collection_before_biller_settlement(): void
    {
        Carbon::setTestNow('2026-09-28 08:00:00');
        [$user, $spaceId, $account] = $this->customerContext();

        DB::table('financial_accounts')->insert([
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'display_name' => 'Recorded cash',
            'account_type' => 'cash',
            'balance_minor' => 200000,
            'currency' => 'UGX',
            'confidence' => 'user_reported',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = CustomerWallet::create([
            'user_id' => $user->id,
            'provider' => 'mtn',
            'msisdn' => '256700000001',
            'status' => 'active',
            'is_default_repayment' => true,
            'verified_at' => now(),
        ]);

        $mobileMoney = $this->mock(MobileMoneyService::class);
        $mobileMoney->shouldReceive('collect')->once()->andReturnUsing(function (array $attributes) use ($user) {
            return MobileMoneyTransaction::create([
                'user_id' => $user->id,
                'provider' => 'cpay',
                'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
                'amount_minor' => $attributes['amount_minor'],
                'currency' => $attributes['currency'],
                'phone' => $attributes['phone'],
                'idempotency_key' => $attributes['idempotency_key'],
                'internal_reference' => $attributes['internal_reference'],
                'provider_reference' => 'CPAY-COLLECT-001',
                'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
                'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
                'metadata' => ['purpose' => $attributes['purpose']],
            ]);
        });

        $cpay = $this->mock(CpayEssentialsClient::class);
        $cpay->shouldReceive('configuredForBiller')->once()->andReturn(true);
        $cpay->shouldReceive('payOwnMoneyBill')->once()->withArgs(function ($payment, $providedAccount, $biller, $collectionReference) use ($account) {
            return (int) $providedAccount->id === (int) $account->id
                && $biller->code === 'UEDCL'
                && $collectionReference === 'CPAY-COLLECT-001'
                && $payment->status === 'fulfilment_pending';
        })->andReturn([
            'status' => 'SUCCESS',
            'providerReference' => 'CPAY-BILL-OWN-001',
        ]);

        Sanctum::actingAs($user);
        $assessment = $this->postJson('/api/essentials/affordability', [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'bill_amount_minor' => 50000,
            'bill_due_date' => '2026-09-29',
            'horizon_end' => '2026-10-15',
            'currency' => 'UGX',
        ])->assertCreated()->json('data.assessment');

        $payment = $this->postJson('/api/essentials/own-money-payments', [
            'financial_space_id' => $spaceId,
            'essentials_account_id' => $account->id,
            'affordability_assessment_id' => $assessment['id'],
            'wallet_id' => $wallet->id,
            'amount_minor' => 50000,
            'idempotency_key' => 'OWN-MONEY-001',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', 'successful')
            ->assertJsonPath('data.payment.provider_reference', 'CPAY-BILL-OWN-001')
            ->json('data.payment');

        $this->assertNotNull($payment['settled_at']);
        $this->assertDatabaseHas('essentials_payment_events', [
            'own_money_payment_id' => $payment['id'],
            'event_type' => 'settled',
            'amount_minor' => 50000,
        ]);
        $this->assertDatabaseHas('essentials_own_money_payments', [
            'id' => $payment['id'],
            'collection_transaction_id' => 1,
            'status' => 'successful',
        ]);
    }

    private function customerContext(): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'phone' => '256700000001',
            'phone_verified_at' => now(),
        ]);

        $spaceId = DB::table('financial_spaces')->where('type', 'personal')->whereExists(function ($query) use ($user) {
            $query->selectRaw('1')
                ->from('financial_space_memberships')
                ->whereColumn('financial_space_memberships.financial_space_id', 'financial_spaces.id')
                ->where('financial_space_memberships.user_id', $user->id)
                ->where('financial_space_memberships.status', 'active');
        })->value('id');

        if (! $spaceId) {
            $spaceId = DB::table('financial_spaces')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'type' => 'personal',
                'name' => 'My Money',
                'country' => 'UG',
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $spaceId,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $biller = EssentialsBiller::query()->where('code', 'UEDCL')->firstOrFail();
        $reference = '04200000001';
        $account = EssentialsAccount::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'financial_space_id' => $spaceId,
            'biller_id' => $biller->id,
            'account_reference' => $reference,
            'account_reference_hash' => hash('sha256', $reference),
            'verification_status' => 'verified',
            'provider_reference' => 'UEDCL-TEST-ACCOUNT',
            'verified_at' => now(),
            'metadata' => [],
        ]);

        return [$user, (int) $spaceId, $account];
    }
}
