<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavingsGroupCyclesTest extends TestCase
{
    use RefreshDatabase;

    private function setupGroup(): array
    {
        $owner = User::factory()->create();
        $treasurer = User::factory()->create();
        $member = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/financial-spaces', [
            'type' => 'savings_group', 'name' => 'Village Savings',
        ])->assertCreated()->json('data.space.id');
        foreach ([$treasurer->id => 'treasurer', $member->id => 'member'] as $userId => $role) {
            DB::table('financial_space_memberships')->insert([
                'financial_space_id' => $id, 'user_id' => $userId,
                'role' => $role, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$owner, $treasurer, $member, '/api/financial-spaces/'.$id.'/savings-groups'];
    }

    public function test_cycle_activation_needs_independent_official_and_contributions_need_matching(): void
    {
        [$owner, $treasurer, $member, $base] = $this->setupGroup();
        $cycle = $this->postJson($base.'/cycles', [
            'name' => 'Cycle 1', 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-01',
        ])->assertCreated()->json('data.cycle.id');

        $this->postJson($base.'/cycles/'.$cycle.'/activate')->assertForbidden();
        Sanctum::actingAs($member);
        $this->postJson($base.'/cycles/'.$cycle.'/activate')->assertForbidden();
        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/cycles/'.$cycle.'/activate')->assertOk()
            ->assertJsonPath('data.cycle.status', 'open');

        Sanctum::actingAs($member);
        $url = $base.'/cycles/'.$cycle.'/contributions';
        $pledge = [
            'kind' => 'savings', 'amount_minor' => 30000,
            'idempotency_key' => 'member-one-savings-2026-09',
        ];
        $id = $this->postJson($url, $pledge)->assertCreated()
            ->assertJsonPath('data.contribution.status', 'pledged')
            ->json('data.contribution.id');
        $this->postJson($url, $pledge)->assertCreated()->assertJsonPath('data.contribution.id', $id);
        $this->postJson($url, [...$pledge, 'amount_minor' => 60000])->assertStatus(409);
        $this->postJson($url.'/'.$id.'/verify', ['transaction_id' => 999999])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson($url.'/'.$id.'/verify', ['transaction_id' => 999999])->assertStatus(422);

        $spaceId = (int) explode('/', $base)[3];
        $account = $this->postJson('/api/financial-spaces/'.$spaceId.'/treasury/accounts', [
            'account_name' => 'Cashbox', 'account_type' => 'cash', 'currency' => 'UGX',
        ])->assertCreated()->json('data.account.id');

        $transaction = $this->postJson('/api/financial-spaces/'.$spaceId.'/treasury/accounts/'.$account.'/transactions', [
            'direction' => 'credit', 'amount_minor' => 30000,
            'currency' => 'UGX',
            'description' => 'Member savings',
            'transaction_date' => '2026-09-15',
            'idempotency_key' => 'savings-deposit-001',
        ])->assertCreated()->json('data.transaction.id');
        $this->postJson($url.'/'.$id.'/verify', ['transaction_id' => $transaction])->assertStatus(422);
        DB::table('financial_space_transactions')->where('id', $transaction)
            ->update(['reconciliation_status' => 'matched', 'reconciled_at' => now()]);
        $this->postJson($url.'/'.$id.'/verify', ['transaction_id' => $transaction])
            ->assertOk()->assertJsonPath('data.contribution.status', 'verified');
        $this->postJson($url.'/'.$id.'/verify', ['transaction_id' => $transaction])->assertStatus(409);

        $this->postJson($base.'/cycles/'.$cycle.'/close')->assertOk()
            ->assertJsonPath('data.cycle.status', 'closed');
        $this->postJson($base.'/cycles/'.$cycle.'/shareouts/prepare')->assertCreated()
            ->assertJsonPath('data.allocation_count', 1)
            ->assertJsonPath('data.status', 'proposed_only');
        $allocation = $this->getJson($base.'/cycles/'.$cycle.'/shareouts')
            ->assertOk()->assertJsonPath('data.allocations.0.principal_minor', 30000)
            ->json('data.allocations.0.id');
        $this->postJson($base.'/cycles/'.$cycle.'/shareouts/'.$allocation.'/approve')->assertForbidden();
        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/cycles/'.$cycle.'/shareouts/'.$allocation.'/approve')
            ->assertOk()->assertJsonPath('data.allocation.status', 'approved_not_paid');
    }

    public function test_loans_and_social_claims_remain_requests_not_fabricated_payments(): void
    {
        [$owner, $treasurer, $member, $base] = $this->setupGroup();
        $cycle = $this->postJson($base.'/cycles', [
            'name' => 'Cycle 2', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31',
        ])->assertCreated()->json('data.cycle.id');
        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/cycles/'.$cycle.'/activate')->assertOk();

        Sanctum::actingAs($member);
        $loan = $this->postJson($base.'/cycles/'.$cycle.'/loans', [
            'principal_minor' => 50000, 'term_days' => 28,
            'interest_rate_bps' => 250,
        ])->assertCreated()->assertJsonPath('data.disbursement_enabled', false)
            ->json('data.loan_request.id');
        $this->postJson($base.'/cycles/'.$cycle.'/loans/'.$loan.'/approve')->assertForbidden();

        $claim = $this->postJson($base.'/cycles/'.$cycle.'/social-claims', [
            'amount_minor' => 25000, 'reason' => 'Emergency family support request',
        ])->assertCreated()->json('data.claim.id');

        Sanctum::actingAs($treasurer);
        $this->postJson($base.'/cycles/'.$cycle.'/loans/'.$loan.'/approve')
            ->assertOk()->assertJsonPath('data.loan_request.status', 'approved_not_disbursed');
        $this->postJson($base.'/cycles/'.$cycle.'/social-claims/'.$claim.'/approve')
            ->assertOk()->assertJsonPath('data.claim.status', 'approved_not_paid');
    }

    public function test_foreign_space_and_non_group_requests_are_denied(): void
    {
        [$owner, $treasurer, $member, $base] = $this->setupGroup();
        $id = $this->postJson($base.'/cycles', [
            'name' => 'Our cycle', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31',
        ])->assertCreated()->json('data.cycle.id');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($base.'/cycles')->assertForbidden();
        $this->postJson($base.'/cycles/'.$id.'/activate')->assertForbidden();
        $this->postJson($base.'/cycles/'.$id.'/contributions', [
            'kind' => 'savings', 'amount_minor' => 1000,
            'idempotency_key' => 'foreign-contribution-00001',
        ])->assertForbidden();
        $this->getJson('/api/financial-spaces/999999/savings-groups/cycles')->assertNotFound();
    }
}
