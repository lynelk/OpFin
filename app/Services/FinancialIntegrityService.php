<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerTransaction;
use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialIntegrityService
{
    public function __construct(private readonly LongRangeIntegrityService $longRangeIntegrity) {}

    public function run(string $scope = 'platform'): object
    {
        $runId = DB::table('financial_integrity_runs')->insertGetId([
            'status' => 'running',
            'scope' => $scope,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $findings = [];
        $ledgerChecked = 0;
        $unbalanced = 0;
        $netImbalance = 0;
        $orphanEntries = 0;
        $duplicateReferences = 0;
        $paymentExceptions = 0;

        if (Schema::hasTable('ledger_transactions') && Schema::hasTable('ledger_entries')) {
            $balances = DB::table('ledger_transactions as t')
                ->leftJoin('ledger_entries as e', 'e.ledger_transaction_id', '=', 't.id')
                ->select('t.id', 't.reference', 't.currency')
                ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'debit' THEN e.amount_minor ELSE 0 END), 0) AS debit_minor")
                ->selectRaw("COALESCE(SUM(CASE WHEN e.direction = 'credit' THEN e.amount_minor ELSE 0 END), 0) AS credit_minor")
                ->groupBy('t.id', 't.reference', 't.currency')
                ->get();

            $ledgerChecked = $balances->count();
            foreach ($balances as $balance) {
                $difference = (int) $balance->debit_minor - (int) $balance->credit_minor;
                if ($difference === 0) {
                    continue;
                }
                $unbalanced++;
                $netImbalance += $difference;
                $findings[] = $this->alert($runId, 'critical', 'ledger_unbalanced', $balance->reference,
                    'Ledger transaction debits and credits do not balance.', [
                        'debit_minor' => (int) $balance->debit_minor,
                        'credit_minor' => (int) $balance->credit_minor,
                        'difference_minor' => $difference,
                        'currency' => $balance->currency,
                    ]);
            }

            $orphanEntries = DB::table('ledger_entries as e')
                ->leftJoin('ledger_transactions as t', 't.id', '=', 'e.ledger_transaction_id')
                ->whereNull('t.id')->count();
            if ($orphanEntries > 0) {
                $findings[] = $this->alert($runId, 'critical', 'orphan_ledger_entries', null,
                    'Ledger entries exist without a parent transaction.', ['count' => $orphanEntries]);
            }

            $duplicateReferences = DB::table('ledger_transactions')
                ->select('reference')->groupBy('reference')->havingRaw('count(*) > 1')->count();
            if ($duplicateReferences > 0) {
                $findings[] = $this->alert($runId, 'critical', 'duplicate_ledger_reference', null,
                    'Duplicate immutable ledger references were detected.', ['count' => $duplicateReferences]);
            }
        }

        if (Schema::hasTable('mobile_money_transactions')) {
            $paymentExceptions = MobileMoneyTransaction::query()
                ->where(function ($query) {
                    $query->where('reconciliation_status', MobileMoneyTransaction::RECONCILIATION_EXCEPTION)
                        ->orWhere(function ($query) {
                            $query->where('status', MobileMoneyTransaction::STATUS_SUCCESSFUL)
                                ->where('reconciliation_status', '!=', MobileMoneyTransaction::RECONCILIATION_MATCHED);
                        });
                })->count();

            if ($paymentExceptions > 0) {
                $findings[] = $this->alert($runId, 'high', 'payment_reconciliation_exception', null,
                    'Successful or excepted payment records are not fully reconciled.', ['count' => $paymentExceptions]);
            }

            $duplicateProviderRefs = MobileMoneyTransaction::query()
                ->whereNotNull('provider_reference')->select('provider_reference')
                ->groupBy('provider_reference')->havingRaw('count(*) > 1')->pluck('provider_reference');
            foreach ($duplicateProviderRefs as $reference) {
                $findings[] = $this->alert($runId, 'critical', 'duplicate_provider_reference', $reference,
                    'The same provider reference appears on multiple money-movement records.', ['provider_reference' => $reference]);
            }
        }

        $this->scanExpectedCreditPostings($runId, $findings);
        $this->scanAssetEconomics($runId, $findings);

        foreach ($this->longRangeIntegrity->scan() as $finding) {
            $findings[] = $this->alert(
                $runId,
                $finding['severity'],
                $finding['type'],
                $finding['reference'],
                $finding['description'],
                $finding['evidence'],
            );
        }

        $canonical = json_encode($findings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $status = collect($findings)->contains(fn ($finding) => ($finding['severity'] ?? null) === 'critical')
            ? 'critical'
            : (count($findings) > 0 ? 'exceptions' : 'balanced');

        DB::table('financial_integrity_runs')->where('id', $runId)->update([
            'status' => $status,
            'ledger_transactions_checked' => $ledgerChecked,
            'unbalanced_transactions' => $unbalanced,
            'payment_exceptions' => $paymentExceptions,
            'duplicate_references' => $duplicateReferences,
            'orphan_entries' => $orphanEntries,
            'net_ledger_imbalance_minor' => $netImbalance,
            'findings' => $canonical,
            'evidence_hash' => hash('sha256', $canonical),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('financial_integrity_runs')->find($runId);
    }

    public function summary(): array
    {
        $latest = Schema::hasTable('financial_integrity_runs')
            ? DB::table('financial_integrity_runs')->latest('id')->first()
            : null;

        return [
            'latest_run' => $latest,
            'open_critical_alerts' => Schema::hasTable('financial_integrity_alerts')
                ? DB::table('financial_integrity_alerts')->where('status', 'open')->where('severity', 'critical')->count()
                : 0,
            'open_high_alerts' => Schema::hasTable('financial_integrity_alerts')
                ? DB::table('financial_integrity_alerts')->where('status', 'open')->where('severity', 'high')->count()
                : 0,
            'platform_balanced' => $latest?->status === 'balanced',
            'funds_integrity_rule' => 'Every governed financial event must have exactly the expected economic posting. Any missing posting, imbalance, false settlement, funding mismatch, reward-ledger mismatch, duplicate provider reference, or unreconciled successful payment is an exception and cannot be silently written off or auto-balanced away.',
        ];
    }

    private function scanExpectedCreditPostings(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('credit_offers') || ! Schema::hasTable('mobile_money_transactions') || ! Schema::hasTable('ledger_transactions')) {
            return;
        }

        $transactions = MobileMoneyTransaction::query()
            ->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)
            ->whereNotNull('credit_offer_id')
            ->whereIn('status', [MobileMoneyTransaction::STATUS_SUCCESSFUL, MobileMoneyTransaction::STATUS_REVERSED])
            ->get(['id', 'credit_offer_id', 'loan_id', 'status', 'provider_reference']);

        foreach ($transactions as $transaction) {
            $offer = CreditOffer::query()->find($transaction->credit_offer_id);
            if (! $offer) {
                $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_offer', (string) $transaction->id,
                    'Production disbursement references a missing credit offer.', ['mobile_money_transaction_id' => $transaction->id]);
                continue;
            }

            $originalReference = 'loan.disbursement:credit-offer:'.$offer->offer_reference;
            if ($transaction->status === MobileMoneyTransaction::STATUS_SUCCESSFUL && ! LedgerTransaction::query()->where('reference', $originalReference)->exists()) {
                $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_ledger_posting', $offer->offer_reference,
                    'Successful production credit disbursement has no required immutable ledger posting.', [
                        'mobile_money_transaction_id' => $transaction->id,
                        'credit_offer_id' => $offer->id,
                        'loan_id' => $transaction->loan_id,
                        'expected_ledger_reference' => $originalReference,
                    ]);
            }

            if ($transaction->status === MobileMoneyTransaction::STATUS_REVERSED && $transaction->loan_id) {
                $reversalReference = 'loan.disbursement.reversal:credit-offer:'.$offer->offer_reference;
                $loanStatus = DB::table('loans')->where('id', $transaction->loan_id)->value('status');
                if ($loanStatus !== 'Exception' && ! LedgerTransaction::query()->where('reference', $reversalReference)->exists()) {
                    $findings[] = $this->alert($runId, 'critical', 'credit_disbursement_missing_reversal_posting', $offer->offer_reference,
                        'Provider-reversed production disbursement still lacks its append-only economic reversal posting.', [
                            'mobile_money_transaction_id' => $transaction->id,
                            'credit_offer_id' => $offer->id,
                            'loan_id' => $transaction->loan_id,
                            'expected_reversal_reference' => $reversalReference,
                        ]);
                }
            }
        }
    }

    private function scanAssetEconomics(int $runId, array &$findings): void
    {
        if (! Schema::hasTable('asset_finance_requests')) {
            return;
        }

        $records = DB::table('asset_finance_requests')->get(['reference', 'asset_price_minor', 'deposit_minor', 'status', 'decision_evidence']);
        foreach ($records as $record) {
            $assetPrice = (int) $record->asset_price_minor;
            $deposit = (int) $record->deposit_minor;
            if ($assetPrice <= 0 || $deposit < 0 || $deposit >= $assetPrice) {
                $findings[] = $this->alert($runId, 'critical', 'asset_finance_invalid_economics', $record->reference,
                    'Asset-finance request has an invalid asset-price/deposit relationship.', [
                        'asset_price_minor' => $assetPrice,
                        'deposit_minor' => $deposit,
                    ]);
                continue;
            }

            if (in_array($record->status, ['approved', 'deposit_settled'], true)) {
                $evidence = (array) json_decode((string) ($record->decision_evidence ?? '{}'), true);
                $approved = isset($evidence['approved_amount_minor']) ? (int) $evidence['approved_amount_minor'] : 0;
                $maximum = $assetPrice - $deposit;
                if ($approved <= 0 || $approved > $maximum) {
                    $findings[] = $this->alert($runId, 'critical', 'asset_finance_approval_mismatch', $record->reference,
                        'Approved asset-finance amount does not reconcile to asset price less deposit.', [
                            'approved_amount_minor' => $approved,
                            'maximum_finance_minor' => $maximum,
                            'asset_price_minor' => $assetPrice,
                            'deposit_minor' => $deposit,
                        ]);
                }
            }
        }
    }

    private function alert(int $runId, string $severity, string $type, ?string $reference, string $description, array $evidence): array
    {
        $query = DB::table('financial_integrity_alerts')->where('status', 'open')->where('type', $type);
        $reference === null ? $query->whereNull('reference') : $query->where('reference', $reference);
        $existing = $query->first();

        if ($existing) {
            DB::table('financial_integrity_alerts')->where('id', $existing->id)->update([
                'run_id' => $runId,
                'severity' => $severity,
                'description' => $description,
                'evidence' => json_encode($evidence),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('financial_integrity_alerts')->insert([
                'run_id' => $runId,
                'severity' => $severity,
                'type' => $type,
                'reference' => $reference,
                'description' => $description,
                'status' => 'open',
                'evidence' => json_encode($evidence),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('autopilot_work_items')) {
            DB::table('autopilot_work_items')->updateOrInsert([
                'domain' => 'financial_integrity',
                'type' => $type,
                'subject_type' => 'financial_integrity_alert',
                'subject_reference' => $reference ?? $type,
                'status' => 'open',
            ], [
                'severity' => $severity,
                'title' => 'Financial integrity exception',
                'description' => $description,
                'recommended_action' => 'Investigate source records and provider evidence. Use append-only corrections; never create a balancing entry solely to make the exception disappear.',
                'confidence' => 1,
                'automation_tier' => 'A5',
                'requires_human' => true,
                'context' => json_encode($evidence),
                'due_at' => now()->addHour(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        return compact('severity', 'type', 'reference', 'description', 'evidence');
    }
}
