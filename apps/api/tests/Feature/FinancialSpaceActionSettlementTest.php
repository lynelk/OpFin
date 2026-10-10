<?php

namespace Tests\Feature;

use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\FinancialSpaceActionSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialSpaceActionSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function operation(int $amount = 50000): array
    {
        $maker = User::factory()->create();
        $space = DB::table('financial_spaces')->insertGetId([
            'public_id' => (string) Str::uuid(), 'type' => 'investment_club',
            'name' => 'Settlement Test Club', 'country' => 'UG',
            'currency' => 'UGX', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $reference = (string) Str::uuid();
        $id = DB::table('financial_space_action_intents')->insertGetId([
            'public_id' => $reference, 'financial_space_id' => $space,
            'initiated_by_user_id' => $maker->id,
            'action_type' => 'contribution', 'direction' => 'collection',
            'principal_amount_minor' => $amount, 'platform_fee_minor' => 0,
            'total_amount_minor' => $amount, 'currency' => 'UGX',
            'counterparty_phone' => '256700000001', 'status' => 'submitted',
            'idempotency_key' => 'space-test-'.$reference,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tx = MobileMoneyTransaction::create([
            'provider' => 'cpay', 'direction' => 'collection',
            'amount_minor' => $amount, 'currency' => 'UGX',
            'phone' => '256700000001',
            'idempotency_key' => 'space-action:'.$reference,
            'internal_reference' => 'OPF-SPACE-'.$reference,
            'provider_reference' => 'provider-'.$reference,
            'status' => MobileMoneyTransaction::STATUS_SUCCESSFUL,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
            'statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_MATCHED,
            'metadata' => [
                'purpose' => 'financial_space_action', 'source_type' => 'financial_space_action',
                'source_id' => $id,
            ],
        ]);

        return [$id, $tx];
    }

    public function test_provider_statement_match_keeps_club_book_unallocated(): void
    {
        [$id, $tx] = $this->operation();
        $service = app(FinancialSpaceActionSettlementService::class);
        $result = $service->recordProviderStatementMatch($tx);
        $this->assertSame('statement_matched_unallocated', $result->status);
        $this->assertNull($result->book_allocation_reference);
        $this->assertNull($result->book_allocation_approved_at);
        $service->recordProviderStatementMatch($tx);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseHas('financial_space_action_intents', [
            'id' => $id, 'mobile_money_transaction_id' => $tx->id,
            'status' => 'statement_matched_unallocated',
        ]);
    }

    public function test_unmatched_provider_report_does_not_settle_any_space_cash(): void
    {
        [$id, $tx] = $this->operation();
        $tx->update(['statement_reconciliation_status' => MobileMoneyTransaction::STATEMENT_UNRECONCILED]);
        app(FinancialSpaceActionSettlementService::class)->recordProviderStatementMatch($tx->fresh());
        $this->assertDatabaseHas('financial_space_action_intents', ['id' => $id, 'status' => 'submitted']);
    }

    public function test_changed_amount_is_a_separate_exception_not_a_club_credit(): void
    {
        [$id, $tx] = $this->operation();
        $tx->update(['amount_minor' => 99999]);
        $result = app(FinancialSpaceActionSettlementService::class)->recordProviderStatementMatch($tx->fresh());
        $this->assertSame('settlement_exception', $result->status);
        $this->assertSame('instruction_mismatch', $result->provider_statement_status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }

    public function test_statement_matched_reversal_requires_book_correction_not_cash_credit(): void
    {
        [$id, $tx] = $this->operation();
        $tx->update(['status' => MobileMoneyTransaction::STATUS_REVERSED]);
        $result = app(FinancialSpaceActionSettlementService::class)->recordProviderStatementMatch($tx->fresh());
        $this->assertSame('reversed_requires_book_correction', $result->status);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }
}
