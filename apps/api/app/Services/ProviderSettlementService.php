<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\MobileMoneyTransaction;
use App\Models\ProviderSettlementBatch;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderSettlementService
{
    public function __construct(private readonly ProductionLedgerService $ledger) {}

    public function settle(
        ReconciliationRun $run,
        string $currency,
        int $providerFeeMinor,
        int $bankNetSettlementMinor,
        string $bankReference,
        array $evidence,
        User $actor,
    ): ProviderSettlementBatch {
        $currency = strtoupper(trim($currency));
        $bankReference = trim($bankReference);

        if ($run->status !== ReconciliationRun::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Provider settlement requires a completed reconciliation run.');
        }
        if ($providerFeeMinor < 0) {
            throw new InvalidArgumentException('Provider settlement fee cannot be negative.');
        }
        if ($bankReference === '' || $evidence === []) {
            throw new InvalidArgumentException('Provider settlement requires bank reference and supporting evidence.');
        }

        $summary = $run->summary ?? [];
        if ((int) ($summary['exception_count'] ?? 0) !== 0 || (int) ($summary['pending_provider_match_count'] ?? 0) !== 0) {
            throw new InvalidArgumentException('Provider settlement is blocked while the reconciliation run contains exceptions or unmatched records.');
        }

        return DB::transaction(function () use (
            $run, $currency, $providerFeeMinor, $bankNetSettlementMinor, $bankReference, $evidence, $actor
        ) {
            $lockedRun = ReconciliationRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            $existing = ProviderSettlementBatch::query()
                ->where('reconciliation_run_id', $lockedRun->id)
                ->where('currency', $currency)
                ->first();
            if ($existing) {
                if (
                    (int) $existing->provider_fee_minor !== $providerFeeMinor
                    || (int) $existing->bank_net_settlement_minor !== $bankNetSettlementMinor
                    || $existing->bank_reference !== $bankReference
                ) {
                    throw new InvalidArgumentException('This reconciliation run and currency were already settled with different evidence.');
                }

                return $existing;
            }

            $transactionIds = ReconciliationItem::query()
                ->where('reconciliation_run_id', $lockedRun->id)
                ->where('status', ReconciliationItem::STATUS_MATCHED)
                ->where('currency', $currency)
                ->whereNotNull('mobile_money_transaction_id')
                ->pluck('mobile_money_transaction_id');

            $transactions = MobileMoneyTransaction::query()
                ->whereIn('id', $transactionIds)
                ->whereIn('status', [
                    MobileMoneyTransaction::STATUS_SUCCESSFUL,
                    MobileMoneyTransaction::STATUS_REVERSED,
                ])
                ->where('statement_reconciliation_status', MobileMoneyTransaction::STATEMENT_MATCHED)
                ->whereIn('accounting_status', [
                    MobileMoneyTransaction::ACCOUNTING_POSTED,
                    MobileMoneyTransaction::ACCOUNTING_NOT_REQUIRED,
                ])
                ->where('currency', $currency)
                ->get();

            if ($transactions->isEmpty()) {
                throw new InvalidArgumentException('Provider settlement has no matched successful transactions for this currency.');
            }

            $collectionsMinor = (int) $transactions
                ->where('direction', MobileMoneyTransaction::DIRECTION_COLLECTION)
                ->sum(fn (MobileMoneyTransaction $transaction) =>
                    $transaction->status === MobileMoneyTransaction::STATUS_REVERSED
                        ? -1 * (int) $transaction->amount_minor
                        : (int) $transaction->amount_minor
                );
            $disbursementsMinor = (int) $transactions
                ->where('direction', MobileMoneyTransaction::DIRECTION_DISBURSEMENT)
                ->sum(fn (MobileMoneyTransaction $transaction) =>
                    $transaction->status === MobileMoneyTransaction::STATUS_REVERSED
                        ? -1 * (int) $transaction->amount_minor
                        : (int) $transaction->amount_minor
                );
            $expectedNetMinor = $collectionsMinor - $disbursementsMinor - $providerFeeMinor;

            if ($bankNetSettlementMinor !== $expectedNetMinor) {
                throw new InvalidArgumentException(
                    "Bank settlement does not reconcile to provider activity. expected={$expectedNetMinor}, actual={$bankNetSettlementMinor}."
                );
            }

            $canonicalEvidence = [
                'reconciliation_run_id' => $lockedRun->id,
                'provider' => strtolower((string) $lockedRun->provider),
                'business_date' => $lockedRun->business_date->toDateString(),
                'currency' => $currency,
                'collections_minor' => $collectionsMinor,
                'disbursements_minor' => $disbursementsMinor,
                'provider_fee_minor' => $providerFeeMinor,
                'bank_net_settlement_minor' => $bankNetSettlementMinor,
                'bank_reference' => $bankReference,
                'transaction_ids' => $transactions->pluck('id')->sort()->values()->all(),
                'supporting_evidence' => $evidence,
            ];
            $evidenceJson = json_encode($canonicalEvidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $batch = ProviderSettlementBatch::create([
                'reconciliation_run_id' => $lockedRun->id,
                'provider' => strtolower((string) $lockedRun->provider),
                'currency' => $currency,
                'business_date' => $lockedRun->business_date->toDateString(),
                'collections_minor' => $collectionsMinor,
                'disbursements_minor' => $disbursementsMinor,
                'provider_fee_minor' => $providerFeeMinor,
                'bank_net_settlement_minor' => $bankNetSettlementMinor,
                'bank_reference' => $bankReference,
                'evidence' => $canonicalEvidence,
                'evidence_hash' => hash('sha256', $evidenceJson),
                'settled_by' => $actor->id,
                'settled_at' => now(),
            ]);

            $entries = [];
            $provider = strtolower((string) $lockedRun->provider);

            if ($collectionsMinor !== 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'cash.'.$provider.'.collection',
                        ucfirst($provider).' collection clearing',
                        'asset',
                        $currency,
                    )->id,
                    'direction' => $collectionsMinor > 0
                        ? LedgerEntry::DIRECTION_CREDIT
                        : LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => abs($collectionsMinor),
                    'memo' => $collectionsMinor > 0
                        ? 'Clear provider-confirmed collections into bank settlement'
                        : 'Clear provider-confirmed collection reversals from bank settlement',
                ];
            }

            if ($disbursementsMinor !== 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'cash.'.$provider.'.disbursement',
                        ucfirst($provider).' disbursement clearing',
                        'asset',
                        $currency,
                    )->id,
                    'direction' => $disbursementsMinor > 0
                        ? LedgerEntry::DIRECTION_DEBIT
                        : LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => abs($disbursementsMinor),
                    'memo' => $disbursementsMinor > 0
                        ? 'Clear provider-confirmed disbursements against bank settlement'
                        : 'Clear provider-confirmed disbursement reversals into bank settlement',
                ];
            }

            if ($providerFeeMinor > 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'expense.provider_fees.'.$provider,
                        ucfirst($provider).' provider fees',
                        'expense',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $providerFeeMinor,
                    'memo' => 'Recognise provider settlement fees',
                ];
            }

            if ($bankNetSettlementMinor > 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'cash.bank.settlement',
                        'Bank settlement cash',
                        'asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $bankNetSettlementMinor,
                    'memo' => 'Net provider settlement received in bank',
                ];
            } elseif ($bankNetSettlementMinor < 0) {
                $entries[] = [
                    'account_id' => $this->account(
                        'cash.bank.settlement',
                        'Bank settlement cash',
                        'asset',
                        $currency,
                    )->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => abs($bankNetSettlementMinor),
                    'memo' => 'Net provider settlement funded from bank',
                ];
            }

            $posted = $this->ledger->post(
                'provider.settlement:'.$batch->id,
                'provider.settlement',
                $batch,
                $entries,
                $actor,
                $currency,
                [
                    'provider' => $provider,
                    'reconciliation_run_id' => $lockedRun->id,
                    'business_date' => $lockedRun->business_date->toDateString(),
                    'bank_reference' => $bankReference,
                    'evidence_hash' => $batch->evidence_hash,
                ],
            );

            $batch->update(['ledger_transaction_id' => $posted->id]);

            return $batch->fresh();
        });
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code, 'currency' => strtoupper($currency)],
            ['name' => $name, 'type' => $type, 'is_active' => true],
        );
    }
}
