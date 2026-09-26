<?php

namespace Tests\Feature;

use App\Models\FinancialSpaceTreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LaunchSafetyRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_receipts_and_payments_does_not_move_the_opening_baseline(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 25));
        $owner = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($owner);
        $space = $this->postJson('/api/financial-spaces', [
            'type' => 'investment_club', 'name' => 'Synthetic Launch Club',
            'country' => 'UG', 'currency' => 'UGX',
        ])->assertCreated()->json('data.space.id');
        $account = $this->postJson("/api/financial-spaces/{$space}/treasury/accounts", [
            'account_name' => 'Synthetic Operating Account', 'account_type' => 'bank',
            'currency' => 'UGX', 'opening_balance_minor' => 1000000,
            'balance_as_of' => '2026-09-01',
        ])->assertCreated()->json('data.account.id');

        foreach ([['credit', 250000, '2026-09-05'], ['debit', 100000, '2026-09-10']] as $index => [$direction, $amount, $date]) {
            $this->postJson("/api/financial-spaces/{$space}/treasury/accounts/{$account}/transactions", [
                'direction' => $direction, 'amount_minor' => $amount,
                'description' => 'Synthetic historical entry', 'transaction_date' => $date,
                'transaction_reference' => 'LAUNCH-BASELINE-'.$index,
            ])->assertCreated();
            $this->assertSame('2026-09-01', FinancialSpaceTreasuryAccount::findOrFail($account)->balance_as_of->toDateString());
        }
        $this->assertSame(1150000, FinancialSpaceTreasuryAccount::findOrFail($account)->current_balance_minor);
        $this->postJson("/api/financial-spaces/{$space}/treasury/accounts/{$account}/transactions", [
            'direction' => 'credit', 'amount_minor' => 1,
            'description' => 'Before baseline must remain invalid', 'transaction_date' => '2026-08-31',
        ])->assertUnprocessable();
        $this->assertSame(1150000, FinancialSpaceTreasuryAccount::findOrFail($account)->current_balance_minor);
    }

    public function test_partner_api_role_cannot_enter_general_platform_administration(): void
    {
        $this->assertContains('partner_api', User::ROLES);
        $this->assertArrayHasKey('partner_api', User::ROLE_PERMISSIONS);
        $this->assertNotContains('*', User::ROLE_PERMISSIONS['partner_api']);
        Sanctum::actingAs(User::factory()->create(['role' => 'partner_api']));
        $this->getJson('/api/admin/foundation-check')->assertForbidden();
    }
}
