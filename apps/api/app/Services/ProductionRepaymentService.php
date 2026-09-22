<?php

namespace App\Services;

use App\Models\CreditRepaymentScheduleItem;
use App\Models\CustomerWallet;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use App\Models\MobileMoneyTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MobileMoney\MobileMoneyService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionRepaymentService
{
    public const ALLOCATION_POLICY_VERSION = 'oldest-due-interest-fees-principal-v1';

    public function __construct(
        private readonly MobileMoneyService $mobileMoney,
        private readonly LoanService $loanService,
        private readonly ProductionLoanLedgerService $productionLoanLedgerService,
        private readonly AuditLogger $auditLogger,
        private readonly CreditReferenceReportingService $creditReporting,
        private readonly TransactionReceiptService $receipts,
    ) {}

    public function initiate(
        Loan $loan,
        User $user,
        int $amountMinor,
        string $idempotencyKey,
        ?int $walletId = null,
    ): MobileMoneyTransaction {
        if ((int) $loan->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('This loan does not belong to the authenticated customer.');
        }

        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('A repayment idempotency key is required.');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Repayment amount_minor must be a positive integer.');
        }

        $existing = MobileMoneyTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            $this->assertIdempotentReplay($existing, $loan, $amountMinor);
            $this->syncCollectionState($existing);

            return $existing->fresh();
        }

        $lockedLoan = DB::transaction(function () use ($loan, $amountMinor, $idempotencyKey) {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $outstanding = $this->outstandingMinor($locked);

            if (strcasecmp((string) $locked->status, 'Cleared') === 0 || $outstanding <= 0) {
                throw new InvalidArgumentException('This loan has already been fully settled.');
            }
            if ($amountMinor > $outstanding) {
                throw new InvalidArgumentException('Repayment amount exceeds the current outstanding obligation.');
            }

            $pending = MobileMoneyTransaction::query()
                ->where('loan_id', $locked->id)
                ->where('direction', MobileMoneyTransaction::DIRECTION_COLLECTION)
                ->whereIn('status', [
                    MobileMoneyTransaction::STATUS_PROCESSING,
                    MobileMoneyTransaction::STATUS_PENDING,
                ])
                ->where('idempotency_key', '!=', $idempotencyKey)
                ->exists();

            if ($pending) {
                throw new InvalidArgumentException('A repayment collection is already in progress for this loan.');
            }

            return $locked;
        });

        $walletQuery = CustomerWallet::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at');
        $wallet = $walletId
            ? (clone $walletQuery)->whereKey($walletId)->first()
            : (clone $walletQuery)->where('is_default_repayment', true)->first();

        if ($walletId && ! $wallet) {
            throw new InvalidArgumentException('Choose a verified repayment wallet that belongs to your OpFin profile.');
        }

        $wallet ??= (clone $walletQuery)->orderByDesc('is_default_repayment')->first();
        $collectionPhone = $wallet?->msisdn ?? $user->phone;

        $reference = $this->repaymentReference($idempotencyKey);
        $legacyTransaction = Transaction::query()->firstOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $lockedLoan->user_id,
                'institution_id' => $lockedLoan->institution_id,
                'loan_application_id' => $lockedLoan->loan_application_id,
                'loan_id' => $lockedLoan->id,
                'type' => 'Repayment',
                'amount' => $amountMinor,
                'phone' => $collectionPhone,
                'status' => 'Pending',
            ],
        );

        try {
            $mobileMoney = $this->mobileMoney->collect([
                'transaction_id' => $legacyTransaction->id,
                'loan_id' => $lockedLoan->id,
                'user_id' => $lockedLoan->user_id,
                'institution_id' => $lockedLoan->institution_id,
                'amount_minor' => $amountMinor,
                'currency' => (string) config('services.mobile_money.currency', 'UGX'),
                'phone' => $collectionPhone,
                'idempotency_key' => $idempotencyKey,
                'internal_reference' => $reference,
                'description' => 'OpFin loan repayment',
                'purpose' => 'loan_repayment',
                'allocation_policy_version' => self::ALLOCATION_POLICY_VERSION,
            ]);
        } catch (\Throwable $exception) {
            $legacyTransaction->update(['status' => 'FAILED']);
            throw $exception;
        }

        $this->syncCollectionState($mobileMoney);
        $this->auditLogger->record('credit.repayment.collection_requested', $user, $mobileMoney, [
            'loan_id' => $lockedLoan->id,
            'legacy_transaction_id' => $legacyTransaction->id,
            'amount_minor' => $amountMinor,
            'idempotency_key' => $idempotencyKey,
            'wallet_id' => $wallet?->id,
        ]);

        return $mobileMoney->fresh();
    }

    public function syncCollectionState(MobileMoneyTransaction $mobileMoney): ?Loan
    {
        if (
            $mobileMoney->direction !== MobileMoneyTransaction::DIRECTION_COLLECTION
            || ! $mobileMoney->loan_id
            || ! $mobileMoney->transaction_id
        ) {
            return null;
        }

        $transaction = Transaction::query()->find($mobileMoney->transaction_id);
        $loan = Loan::query()->find($mobileMoney->loan_id);
        if (! $transaction || ! $loan) {
            return null;
        }

        $transaction->update([
            'external_reference' => $mobileMoney->provider_reference,
            'status' => match ($mobileMoney->status) {
                MobileMoneyTransaction::STATUS_SUCCESSFUL => 'SUCCESSFUL',
                MobileMoneyTransaction::STATUS_FAILED => 'FAILED',
                MobileMoneyTransaction::STATUS_REVERSED => 'REVERSED',
                default => 'Pending',
            },
        ]);

        if ($mobileMoney->status === MobileMoneyTransaction::STATUS_REVERSED) {
            return $this->reverseSuccessfulCollection($mobileMoney, $transaction, $loan);
        }

        if ($mobileMoney->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            if ($mobileMoney->status === MobileMoneyTransaction::STATUS_FAILED) {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => now(),
                ]);
            }

            return $loan;
        }

        $ledgerReference = 'loan.repayment:'.$transaction->reference;
        $existingLedger = LedgerTransaction::query()->where('reference', $ledgerReference)->first();
        if ($existingLedger) {
            $suspenseMinor = (int) (($existingLedger->metadata ?? [])['suspense_minor'] ?? 0);
            if ($suspenseMinor > 0) {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => $mobileMoney->product_finality_applied_at ?? now(),
                    'failure_reason' => 'Collected funds remain in customer repayment suspense pending refund or approved resolution.',
                ]);
                $transaction->update(['status' => 'Exception']);
            } else {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => $mobileMoney->product_finality_applied_at ?? now(),
                    'failure_reason' => null,
                ]);
            }

            return $loan->fresh();
        }

        if (! $loan->credit_offer_id) {
            $this->loanService->processSuccessfulTransaction($transaction);
            if (LedgerTransaction::query()->where('reference', $ledgerReference)->exists()) {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => now(),
                ]);
                $this->receipts->issue($mobileMoney->fresh(), 'loan_repayment');
            } else {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_EXCEPTION,
                    'failure_reason' => 'Legacy repayment completed without its expected production ledger posting.',
                ]);
            }

            return $loan->fresh();
        }

        return DB::transaction(function () use ($mobileMoney, $transaction, $loan, $ledgerReference) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if (LedgerTransaction::query()->where('reference', $ledgerReference)->exists()) {
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => $mobileMoney->product_finality_applied_at ?? now(),
                ]);

                return $lockedLoan;
            }

            $outstanding = $this->outstandingMinor($lockedLoan);
            if ($mobileMoney->amount_minor > $outstanding) {
                $this->productionLoanLedgerService->postRepayment($transaction, 0, 0, 0);
                $mobileMoney->update([
                    'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                    'product_finality_applied_at' => now(),
                    'failure_reason' => 'Successful collection exceeds the current product obligation. The full collection is held in customer repayment suspense pending refund or approved resolution.',
                ]);
                $transaction->update(['status' => 'Exception']);
                $this->auditLogger->record('credit.repayment.overpayment_exception', null, $mobileMoney, [
                    'loan_id' => $lockedLoan->id,
                    'collected_amount_minor' => $mobileMoney->amount_minor,
                    'outstanding_minor' => $outstanding,
                    'suspense_minor' => $mobileMoney->amount_minor,
                    'ledger_reference' => $ledgerReference,
                ]);
                DB::afterCommit(function () use ($mobileMoney) {
                    $this->receipts->issue(MobileMoneyTransaction::findOrFail($mobileMoney->id), 'loan_repayment');
                });

                return $lockedLoan;
            }

            $allocation = $this->applyProductionAllocation($lockedLoan, $transaction, $mobileMoney, $mobileMoney->amount_minor);
            $this->productionLoanLedgerService->postRepayment(
                $transaction,
                $allocation['interest_minor'],
                $allocation['principal_minor'],
                $allocation['fees_minor'],
            );

            if ($this->outstandingMinor($lockedLoan) === 0) {
                $lockedLoan->update(['status' => 'Cleared']);
            }

            $mobileMoney->update([
                'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                'product_finality_applied_at' => now(),
            ]);
            $this->auditLogger->record('credit.repayment.fulfilled', null, $lockedLoan, [
                'mobile_money_transaction_id' => $mobileMoney->id,
                'provider_reference' => $mobileMoney->provider_reference,
                'amount_minor' => $mobileMoney->amount_minor,
                'principal_minor' => $allocation['principal_minor'],
                'interest_minor' => $allocation['interest_minor'],
                'fees_minor' => $allocation['fees_minor'],
                'allocation_policy_version' => self::ALLOCATION_POLICY_VERSION,
            ]);

            $freshLoan = $lockedLoan->fresh();
            $eventType = $freshLoan->status === 'Cleared' ? 'closure' : 'repayment';
            DB::afterCommit(function () use ($mobileMoney, $freshLoan, $eventType) {
                $this->receipts->issue(MobileMoneyTransaction::findOrFail($mobileMoney->id), 'loan_repayment');
                $this->creditReporting->queueLoanEvent(Loan::findOrFail($freshLoan->id), $eventType);
            });

            return $freshLoan;
        });
    }

    public function outstandingMinor(Loan $loan): int
    {
        if ($loan->credit_offer_id) {
            return (int) CreditRepaymentScheduleItem::query()
                ->where('loan_id', $loan->id)
                ->sum('total_outstanding_minor');
        }

        return (int) round((float) $loan->schedules()->sum('total_outstanding'));
    }

    private function applyProductionAllocation(
        Loan $loan,
        Transaction $transaction,
        MobileMoneyTransaction $mobileMoney,
        int $amountMinor,
    ): array
    {
        $remaining = $amountMinor;
        $interestPaid = 0;
        $feesPaid = 0;
        $principalPaid = 0;

        $items = CreditRepaymentScheduleItem::query()
            ->where('loan_id', $loan->id)
            ->where('total_outstanding_minor', '>', 0)
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($remaining <= 0) {
                break;
            }

            $interest = min($remaining, $item->interest_outstanding_minor);
            $remaining -= $interest;
            $interestPaid += $interest;

            $fees = min($remaining, $item->fees_outstanding_minor);
            $remaining -= $fees;
            $feesPaid += $fees;

            $principal = min($remaining, $item->principal_outstanding_minor);
            $remaining -= $principal;
            $principalPaid += $principal;

            $principalOutstanding = $item->principal_outstanding_minor - $principal;
            $interestOutstanding = $item->interest_outstanding_minor - $interest;
            $feesOutstanding = $item->fees_outstanding_minor - $fees;
            $totalOutstanding = $principalOutstanding + $interestOutstanding + $feesOutstanding;

            $item->update([
                'principal_outstanding_minor' => $principalOutstanding,
                'interest_outstanding_minor' => $interestOutstanding,
                'fees_outstanding_minor' => $feesOutstanding,
                'total_outstanding_minor' => $totalOutstanding,
                'status' => $totalOutstanding === 0
                    ? CreditRepaymentScheduleItem::STATUS_PAID
                    : CreditRepaymentScheduleItem::STATUS_PARTIALLY_PAID,
                'paid_at' => $totalOutstanding === 0 ? now() : null,
            ]);

            if (($principal + $interest + $fees) > 0) {
                DB::table('credit_repayment_allocations')->updateOrInsert(
                    [
                        'transaction_id' => $transaction->id,
                        'credit_repayment_schedule_item_id' => $item->id,
                    ],
                    [
                        'mobile_money_transaction_id' => $mobileMoney->id,
                        'loan_id' => $loan->id,
                        'principal_minor' => $principal,
                        'interest_minor' => $interest,
                        'fees_minor' => $fees,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }

        if ($remaining !== 0) {
            throw new InvalidArgumentException('Repayment allocation did not consume the collected amount exactly.');
        }

        return [
            'principal_minor' => $principalPaid,
            'interest_minor' => $interestPaid,
            'fees_minor' => $feesPaid,
        ];
    }

    private function reverseSuccessfulCollection(
        MobileMoneyTransaction $mobileMoney,
        Transaction $transaction,
        Loan $loan,
    ): Loan {
        $ledgerReference = 'loan.repayment:'.$transaction->reference;
        $reversalReference = 'loan.repayment.reversal:'.$transaction->reference;
        $originalLedger = LedgerTransaction::query()->where('reference', $ledgerReference)->first();

        if (! $originalLedger) {
            $mobileMoney->update([
                'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                'product_finality_applied_at' => now(),
                'failure_reason' => null,
            ]);
            $transaction->update(['status' => 'REVERSED']);

            return $loan->fresh();
        }

        if (LedgerTransaction::query()->where('reference', $reversalReference)->exists()) {
            $mobileMoney->update([
                'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                'product_finality_applied_at' => $mobileMoney->product_finality_applied_at ?? now(),
                'failure_reason' => null,
            ]);
            $transaction->update(['status' => 'REVERSED']);

            return $loan->fresh();
        }

        if (! $loan->credit_offer_id) {
            return $this->markRepaymentReversalException(
                $mobileMoney,
                $transaction,
                $loan,
                $ledgerReference,
                'Legacy repayment reversal requires operations review because exact production allocation evidence is unavailable.',
            );
        }

        return DB::transaction(function () use ($mobileMoney, $transaction, $loan, $ledgerReference, $originalLedger) {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $allocations = DB::table('credit_repayment_allocations')
                ->where('transaction_id', $transaction->id)
                ->orderBy('credit_repayment_schedule_item_id')
                ->get();

            $suspenseMinor = (int) (($originalLedger->metadata ?? [])['suspense_minor'] ?? 0);
            if ($allocations->isEmpty() && $suspenseMinor <= 0) {
                return $this->markRepaymentReversalException(
                    $mobileMoney,
                    $transaction,
                    $lockedLoan,
                    $ledgerReference,
                    'Repayment reversal cannot be automated because exact schedule allocation evidence is missing.',
                );
            }

            foreach ($allocations as $allocation) {
                $item = CreditRepaymentScheduleItem::query()
                    ->whereKey($allocation->credit_repayment_schedule_item_id)
                    ->where('loan_id', $lockedLoan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $principalOutstanding = (int) $item->principal_outstanding_minor + (int) $allocation->principal_minor;
                $interestOutstanding = (int) $item->interest_outstanding_minor + (int) $allocation->interest_minor;
                $feesOutstanding = (int) $item->fees_outstanding_minor + (int) $allocation->fees_minor;

                if (
                    $principalOutstanding > (int) $item->principal_minor
                    || $interestOutstanding > (int) $item->interest_minor
                    || $feesOutstanding > (int) $item->fees_minor
                ) {
                    throw new InvalidArgumentException('Repayment reversal would restore more than the contractual schedule amount.');
                }

                $totalOutstanding = $principalOutstanding + $interestOutstanding + $feesOutstanding;
                $fullyOutstanding = $totalOutstanding === (int) $item->total_due_minor;
                $item->update([
                    'principal_outstanding_minor' => $principalOutstanding,
                    'interest_outstanding_minor' => $interestOutstanding,
                    'fees_outstanding_minor' => $feesOutstanding,
                    'total_outstanding_minor' => $totalOutstanding,
                    'status' => $fullyOutstanding
                        ? ($item->due_date->isPast() ? CreditRepaymentScheduleItem::STATUS_OVERDUE : CreditRepaymentScheduleItem::STATUS_DUE)
                        : CreditRepaymentScheduleItem::STATUS_PARTIALLY_PAID,
                    'paid_at' => null,
                ]);
            }

            $this->productionLoanLedgerService->reverseRepayment($transaction);

            if ($this->outstandingMinor($lockedLoan) > 0 && in_array($lockedLoan->status, ['Cleared', 'Exception'], true)) {
                $lockedLoan->update(['status' => 'Active']);
            }

            $mobileMoney->update([
                'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_APPLIED,
                'product_finality_applied_at' => now(),
                'failure_reason' => null,
            ]);
            $transaction->update(['status' => 'REVERSED']);
            $this->auditLogger->record('credit.repayment.reversed', null, $lockedLoan, [
                'mobile_money_transaction_id' => $mobileMoney->id,
                'provider_reference' => $mobileMoney->provider_reference,
                'ledger_reference' => $ledgerReference,
                'reversal_ledger_reference' => 'loan.repayment.reversal:'.$transaction->reference,
                'allocation_rows_restored' => $allocations->count(),
                'suspense_minor_reversed' => $suspenseMinor,
            ]);

            $freshLoan = $lockedLoan->fresh();
            DB::afterCommit(function () use ($freshLoan) {
                $this->creditReporting->queueLoanEvent(Loan::findOrFail($freshLoan->id), 'correction');
            });

            return $freshLoan;
        });
    }

    private function markRepaymentReversalException(
        MobileMoneyTransaction $mobileMoney,
        Transaction $transaction,
        Loan $loan,
        string $ledgerReference,
        string $reason,
    ): Loan {
        $mobileMoney->update([
            'product_accounting_status' => MobileMoneyTransaction::PRODUCT_ACCOUNTING_EXCEPTION,
            'failure_reason' => $reason,
        ]);
        $transaction->update(['status' => 'Exception']);
        $loan->update(['status' => 'Exception']);
        $this->auditLogger->record('credit.repayment.reversal_exception', null, $mobileMoney, [
            'loan_id' => $loan->id,
            'ledger_reference' => $ledgerReference,
            'provider_reference' => $mobileMoney->provider_reference,
            'reason' => $reason,
        ]);

        return $loan->fresh();
    }

    private function assertIdempotentReplay(
        MobileMoneyTransaction $existing,
        Loan $loan,
        int $amountMinor,
    ): void {
        if (
            $existing->direction !== MobileMoneyTransaction::DIRECTION_COLLECTION
            || (int) $existing->loan_id !== (int) $loan->id
            || (int) $existing->amount_minor !== $amountMinor
        ) {
            throw new InvalidArgumentException('The supplied idempotency key was already used for a different money movement.');
        }
    }

    private function repaymentReference(string $idempotencyKey): string
    {
        return 'OPF-RPY-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 32));
    }
}
