<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProductionCreditController;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProductTerm;
use App\Models\MobileMoneyTransaction;
use App\Models\User;
use App\Services\FinancialIntegrityService;
use App\Services\MobileMoney\MobileMoneyProviderResponse;
use App\Services\MobileMoney\MobileMoneyService;
use App\Services\ProductionLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

class PostMergeFinancialHotfixTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_response_reloads_locked_status_before_transition_validation(): void
    {
        $transaction = MobileMoneyTransaction::create([
            'provider' => 'cpay',
            'direction' => MobileMoneyTransaction::DIRECTION_COLLECTION,
            'amount_minor' => 50000,
            'currency' => 'UGX',
            'phone' => '256700000099',
            'idempotency_key' => 'race-regression-1',
            'internal_reference' => 'race-regression-1',
            'provider_reference' => 'cpay-race-regression-1',
            'status' => MobileMoneyTransaction::STATUS_PENDING,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_PENDING,
        ]);

        DB::table('mobile_money_transactions')->where('id', $transaction->id)->update([
            'status' => MobileMoneyTransaction::STATUS_REVERSED,
            'reconciliation_status' => MobileMoneyTransaction::RECONCILIATION_MATCHED,
        ]);

        $response = new MobileMoneyProviderResponse(
            provider: 'cpay',
            successful: true,
            status: MobileMoneyTransaction::STATUS_SUCCESSFUL,
            providerReference: 'cpay-race-regression-1',
            reconciliationStatus: MobileMoneyTransaction::RECONCILIATION_PENDING,
        );

        $method = new ReflectionMethod(MobileMoneyService::class, 'applyProviderResponse');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reversed -> successful');
        $method->invoke(app(MobileMoneyService::class), $transaction, $response, []);
    }

    public function test_thirty_day_weekly_projection_counts_capped_term_end_instalment(): void
    {
        $term = new LoanProductTerm([
            'interest_rate' => 0,
            'interest_type' => 'Flat',
            'interest_cycle' => 'Weekly',
            'repayment_frequency' => 'Weekly',
            'duration' => 30,
        ]);
        $application = new LoanApplication;
        $application->setRelation('loanProductTerm', $term);

        $method = new ReflectionMethod(ProductionCreditController::class, 'projectedThirtyDayDebtServiceMinor');
        $method->setAccessible(true);

        $projected = $method->invoke(app(ProductionCreditController::class), $application, 100000);

        $this->assertSame(5, Loan::getInstallments(30, 'Weekly'));
        $this->assertSame(100000, $projected);
    }

    public function test_retiring_account_does_not_invalidate_balanced_historical_posting(): void
    {
        $source = User::factory()->create();
        $asset = LedgerAccount::create([
            'code' => 'test.asset.retired-history',
            'name' => 'Historical asset',
            'type' => 'asset',
            'currency' => 'UGX',
            'is_active' => true,
        ]);
        $liability = LedgerAccount::create([
            'code' => 'test.liability.retired-history',
            'name' => 'Historical liability',
            'type' => 'liability',
            'currency' => 'UGX',
            'is_active' => true,
        ]);

        app(ProductionLedgerService::class)->post('test:retired-history', 'test', $source, [
            ['account_id' => $asset->id, 'direction' => 'debit', 'amount_minor' => 1000],
            ['account_id' => $liability->id, 'direction' => 'credit', 'amount_minor' => 1000],
        ]);

        $asset->update(['is_active' => false]);
        $liability->update(['is_active' => false]);

        $run = app(FinancialIntegrityService::class)->run('retired-account-regression');
        $findings = json_decode((string) $run->findings, true, flags: JSON_THROW_ON_ERROR);

        $this->assertFalse(collect($findings)->contains(fn (array $finding) => ($finding['type'] ?? null) === 'invalid_ledger_entry'));
    }
}
