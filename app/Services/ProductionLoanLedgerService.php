<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use App\Models\Transaction;

class ProductionLoanLedgerService
{
    public function __construct(
        private readonly ProductionLedgerService $ledgerService,
        private readonly AuditLogger $auditLogger
    ) {}

    public function postDisbursement(Transaction $transaction, Loan $loan): ?LedgerTransaction
    {
        $reference = $this->ledgerReference('loan.disbursement', $transaction);

        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $amountMinor = $this->toMinorUnits($transaction->amount);
        $providerCashAccount = $this->account(
            'cash.' . strtolower($transaction->network ?: 'unknown') . '.disbursement',
            trim(($transaction->network ?: 'Unknown') . ' disbursement cash'),
            'asset'
        );
        $loanReceivableAccount = $this->loanReceivableAccount($loan);

        $posted = $this->ledgerService->post(
            $reference,
            'loan.disbursement',
            $transaction,
            [
                [
                    'account_id' => $loanReceivableAccount->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $amountMinor,
                    'memo' => 'Loan principal receivable created',
                ],
                [
                    'account_id' => $providerCashAccount->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $amountMinor,
                    'memo' => 'Cash disbursed through mobile money provider',
                ],
            ],
            null,
            'UGX',
            [
                'loan_id' => $loan->id,
                'legacy_transaction_id' => $transaction->id,
                'loan_application_id' => $transaction->loan_application_id,
            ]
        );

        $this->auditLogger->record('ledger.loan_disbursement.posted', null, $posted, [
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
            'amount_minor' => $amountMinor,
        ]);

        return $posted;
    }

    public function postRepayment(
        Transaction $transaction,
        int|float $interestPaid,
        int|float $principalPaid
    ): ?LedgerTransaction {
        $reference = $this->ledgerReference('loan.repayment', $transaction);

        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $amountMinor = $this->toMinorUnits($transaction->amount);
        $interestMinor = $this->toMinorUnits($interestPaid);
        $principalMinor = $this->toMinorUnits($principalPaid);
        $suspenseMinor = $amountMinor - $interestMinor - $principalMinor;

        $entries = [
            [
                'account_id' => $this->account(
                    'cash.' . strtolower($transaction->network ?: 'unknown') . '.collection',
                    trim(($transaction->network ?: 'Unknown') . ' collection cash'),
                    'asset'
                )->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $amountMinor,
                'memo' => 'Cash collected through mobile money provider',
            ],
        ];

        if ($principalMinor > 0) {
            $entries[] = [
                'account_id' => $this->loanReceivableAccount($transaction->loan)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $principalMinor,
                'memo' => 'Loan principal repaid',
            ];
        }

        if ($interestMinor > 0) {
            $entries[] = [
                'account_id' => $this->interestIncomeAccount($transaction->loan)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $interestMinor,
                'memo' => 'Interest income recognized on repayment',
            ];
        }

        if ($suspenseMinor > 0) {
            $entries[] = [
                'account_id' => $this->account('liability.customer_repayment_suspense', 'Customer repayment suspense', 'liability')->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $suspenseMinor,
                'memo' => 'Unallocated repayment amount pending operations review',
            ];
        }

        $posted = $this->ledgerService->post(
            $reference,
            'loan.repayment',
            $transaction,
            $entries,
            null,
            'UGX',
            [
                'loan_id' => $transaction->loan_id,
                'legacy_transaction_id' => $transaction->id,
                'principal_minor' => $principalMinor,
                'interest_minor' => $interestMinor,
                'suspense_minor' => max(0, $suspenseMinor),
            ]
        );

        $this->auditLogger->record('ledger.loan_repayment.posted', null, $posted, [
            'loan_id' => $transaction->loan_id,
            'transaction_id' => $transaction->id,
            'amount_minor' => $amountMinor,
        ]);

        return $posted;
    }

    private function ledgerReference(string $eventType, Transaction $transaction): string
    {
        return $eventType . ':' . $transaction->reference;
    }

    private function loanReceivableAccount(Loan $loan): LedgerAccount
    {
        return $this->account(
            'asset.loan_receivable.product_' . $loan->loan_product_id,
            'Loan receivable product ' . $loan->loan_product_id,
            'asset'
        );
    }

    private function interestIncomeAccount(Loan $loan): LedgerAccount
    {
        return $this->account(
            'income.interest.product_' . $loan->loan_product_id,
            'Interest income product ' . $loan->loan_product_id,
            'income'
        );
    }

    private function account(string $code, string $name, string $type): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'currency' => 'UGX',
                'is_active' => true,
            ]
        );
    }

    private function toMinorUnits(int|float|string $amount): int
    {
        return (int) round((float) $amount);
    }
}
