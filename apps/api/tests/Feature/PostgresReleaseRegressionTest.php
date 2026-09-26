<?php

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\MobileMoney\WebhookSignatureValidator;
use App\Services\RegulatoryFinancialReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresReleaseRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejecting_a_nonce_replay_does_not_abort_the_callers_transaction(): void
    {
        $validator = app(WebhookSignatureValidator::class);
        $body = '{"event_id":"synthetic-transaction-safety"}';
        $secret = 'synthetic-unit-test-key';
        $timestamp = (string) now('UTC')->timestamp;
        $headers = function (string $nonce) use ($body, $secret, $timestamp): array {
            $canonical = implode("\n", ['101', '77', 'synthetic-reference', $timestamp, $nonce, $body]);
            return [
                'X-CPay-Signature-Version' => 'callback-v1',
                'X-CPay-Signature' => base64_encode(hash_hmac('sha256', $canonical, $secret, true)),
                'X-CPay-Timestamp' => $timestamp, 'X-CPay-Nonce' => $nonce,
                'X-CPay-Callback-Task-Id' => '101',
                'X-CPay-Merchant-Id' => '77', 'X-CPay-Reference' => 'synthetic-reference',
            ];
        };
        DB::transaction(function () use ($validator, $headers, $body, $secret): void {
            $this->assertTrue($validator->isValidCpay($body, $headers('synthetic-nonce-one'), $secret));
            $this->assertFalse($validator->isValidCpay($body, $headers('synthetic-nonce-one'), $secret));
            $this->assertSame(1, (int) DB::selectOne('SELECT 1 AS available')->available);
            $this->assertDatabaseCount('cpay_webhook_nonces', 1);
            $this->assertTrue($validator->isValidCpay($body, $headers('synthetic-nonce-two'), $secret));
        });
        $this->assertDatabaseCount('cpay_webhook_nonces', 2);
    }

    public function test_grouped_reconciliation_checks_execute_for_an_empty_period(): void
    {
        $result = app(RegulatoryFinancialReconciliationService::class)->assess(
            Carbon::parse('2026-09-01 00:00:00'), Carbon::parse('2026-09-30 23:59:59')
        );
        $this->assertSame(0, $result['checks']['ledger_imbalances']);
        $this->assertSame(0, $result['checks']['loan_offer_schedule_mismatches']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $result['evidence_hash']);
    }

    public function test_grouped_ledger_exceptions_are_counted_once_and_respect_the_period(): void
    {
        $user = User::factory()->create();
        $debit = LedgerAccount::create(['code' => 'SYNTHETIC_AUDIT_DR', 'name' => 'Synthetic audit debit',
            'type' => 'asset', 'currency' => 'UGX', 'is_active' => true]);
        $credit = LedgerAccount::create(['code' => 'SYNTHETIC_AUDIT_CR', 'name' => 'Synthetic audit credit',
            'type' => 'liability', 'currency' => 'UGX', 'is_active' => true]);
        // Intentionally malformed synthetic books exercise the auditor. These
        // fixtures are not an alternative production ledger-posting path.
        foreach ([['2026-09-05', 1000, 900], ['2026-09-06', 1000, 1000], ['2026-08-31', 500, 100]] as [$date, $dr, $cr]) {
            $transaction = LedgerTransaction::create([
                'reference' => (string) Str::uuid(), 'event_type' => 'synthetic_audit_fixture',
                'currency' => 'UGX', 'source_type' => 'synthetic_test', 'source_id' => $user->id,
                'posted_by' => $user->id, 'posted_at' => Carbon::parse($date), 'metadata' => [],
            ]);
            foreach ([[$debit, 'debit', $dr], [$credit, 'credit', $cr]] as [$account, $direction, $amount]) {
                LedgerEntry::create(['ledger_transaction_id' => $transaction->id, 'ledger_account_id' => $account->id,
                    'direction' => $direction, 'amount_minor' => $amount, 'currency' => 'UGX', 'memo' => 'Synthetic audit fixture']);
            }
        }
        $result = app(RegulatoryFinancialReconciliationService::class)->assess(
            Carbon::parse('2026-09-01 00:00:00'), Carbon::parse('2026-09-30 23:59:59')
        );
        $this->assertSame(1, $result['checks']['ledger_imbalances']);
        $this->assertFalse($result['passed']);
    }
}
