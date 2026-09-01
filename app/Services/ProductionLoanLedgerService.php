<?php

namespace App\Services;

use App\Models\CreditOffer;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Loan;
use App\Models\MobileMoneyTransaction;
use App\Models\Transaction;

class ProductionLoanLedgerService
{
    public function __construct(
        private readonly ProductionLedgerService $ledgerService,
        private readonly AuditLogger $auditLogger
    ) {}

    public function postCreditOfferDisbursement(
        MobileMoneyTransaction $mobileMoney,
        Loan $loan,
        CreditOffer $offer,
    ): ?LedgerTransaction {
        $reference = 'loan.disbursement:credit-offer:'.$offer->offer_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }
        if ($mobileMoney->status !== MobileMoneyTransaction::STATUS_SUCCESSFUL) {
            throw new \InvalidArgumentException('Production credit disbursement can only be posted after successful provider finality.');
        }

        [$principalMinor, $cashMinor, $deductedFeesMinor, $financedFeesMinor] = $this->creditDisbursementComponents($offer);
        $this->assertCreditPaymentMatchesOffer($mobileMoney, $offer, $cashMinor);

        $entries = [
            [
                'account_id' => $this->loanReceivableAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $principalMinor,
                'memo' => 'Loan principal receivable created after provider-confirmed disbursement',
            ],
            [
                'account_id' => $this->providerCashAccount($mobileMoney->provider, 'disbursement', $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $cashMinor,
                'memo' => 'Cash disbursed through governed payment provider',
            ],
        ];

        if ($deductedFeesMinor > 0) {
            $entries[] = [
                'account_id' => $this->creditFeeClearingAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $deductedFeesMinor,
                'memo' => 'Disclosed deducted credit fees held in deferred-fee clearing pending recognition',
            ];
        }

        if ($financedFeesMinor > 0) {
            $entries[] = [
                'account_id' => $this->creditFeeReceivableAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $financedFeesMinor,
                'memo' => 'Financed credit fee receivable created at origination',
            ];
            $entries[] = [
                'account_id' => $this->creditFeeClearingAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $financedFeesMinor,
                'memo' => 'Financed credit fees deferred pending accounting-policy recognition',
            ];
        }

        $posted = $this->ledgerService->post(
            $reference,
            'loan.disbursement',
            $mobileMoney,
            $entries,
            null,
            $offer->currency,
            [
                'loan_id' => $loan->id,
                'credit_offer_id' => $offer->id,
                'mobile_money_transaction_id' => $mobileMoney->id,
                'provider_reference' => $mobileMoney->provider_reference,
                'principal_minor' => $principalMinor,
                'cash_disbursed_minor' => $cashMinor,
                'deducted_fees_minor' => $deductedFeesMinor,
                'financed_fees_minor' => $financedFeesMinor,
                'fee_treatment' => $offer->fee_treatment,
            ],
        );

        $this->auditLogger->record('ledger.loan_disbursement.posted', null, $posted, [
            'loan_id' => $loan->id,
            'credit_offer_id' => $offer->id,
            'mobile_money_transaction_id' => $mobileMoney->id,
            'principal_minor' => $principalMinor,
            'cash_disbursed_minor' => $cashMinor,
            'deducted_fees_minor' => $deductedFeesMinor,
            'financed_fees_minor' => $financedFeesMinor,
        ]);

        return $posted;
    }

    public function reverseCreditOfferDisbursement(
        MobileMoneyTransaction $mobileMoney,
        Loan $loan,
        CreditOffer $offer,
    ): ?LedgerTransaction {
        $originalReference = 'loan.disbursement:credit-offer:'.$offer->offer_reference;
        if (! LedgerTransaction::where('reference', $originalReference)->exists()) {
            throw new \InvalidArgumentException('Cannot reverse a production disbursement that has no original ledger posting.');
        }

        $reference = 'loan.disbursement.reversal:credit-offer:'.$offer->offer_reference;
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }
        if ($mobileMoney->status !== MobileMoneyTransaction::STATUS_REVERSED) {
            throw new \InvalidArgumentException('Production disbursement reversal requires provider-confirmed reversed status.');
        }

        [$principalMinor, $cashMinor, $deductedFeesMinor, $financedFeesMinor] = $this->creditDisbursementComponents($offer);
        $this->assertCreditPaymentMatchesOffer($mobileMoney, $offer, $cashMinor);

        $entries = [
            [
                'account_id' => $this->providerCashAccount($mobileMoney->provider, 'disbursement', $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $cashMinor,
                'memo' => 'Provider-confirmed disbursement reversal restored cash position',
            ],
            [
                'account_id' => $this->loanReceivableAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $principalMinor,
                'memo' => 'Loan principal receivable reversed after provider reversal',
            ],
        ];

        if ($deductedFeesMinor > 0) {
            $entries[] = [
                'account_id' => $this->creditFeeClearingAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $deductedFeesMinor,
                'memo' => 'Deducted deferred-fee clearing reversed with provider reversal',
            ];
        }

        if ($financedFeesMinor > 0) {
            $entries[] = [
                'account_id' => $this->creditFeeClearingAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_DEBIT,
                'amount_minor' => $financedFeesMinor,
                'memo' => 'Financed deferred-fee clearing reversed with provider reversal',
            ];
            $entries[] = [
                'account_id' => $this->creditFeeReceivableAccount($loan, $offer->currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $financedFeesMinor,
                'memo' => 'Financed fee receivable reversed after provider reversal',
            ];
        }

        $posted = $this->ledgerService->post(
            $reference,
            'loan.disbursement.reversal',
            $mobileMoney,
            $entries,
            null,
            $offer->currency,
            [
                'reverses_reference' => $originalReference,
                'loan_id' => $loan->id,
                'credit_offer_id' => $offer->id,
                'mobile_money_transaction_id' => $mobileMoney->id,
                'provider_reference' => $mobileMoney->provider_reference,
                'principal_minor' => $principalMinor,
                'cash_reversed_minor' => $cashMinor,
                'deducted_fees_reversed_minor' => $deductedFeesMinor,
                'financed_fees_reversed_minor' => $financedFeesMinor,
            ],
        );

        $this->auditLogger->record('ledger.loan_disbursement.reversed', null, $posted, [
            'loan_id' => $loan->id,
            'credit_offer_id' => $offer->id,
            'provider_reference' => $mobileMoney->provider_reference,
            'reverses_reference' => $originalReference,
        ]);

        return $posted;
    }

    /** Legacy compatibility path only. */
    public function postDisbursement(Transaction $transaction, Loan $loan): ?LedgerTransaction
    {
        $reference = $this->ledgerReference('loan.disbursement', $transaction);
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $amountMinor = $this->toMinorUnits($transaction->amount);
        $provider = $this->paymentProvider($transaction);
        $currency = 'UGX';
        $posted = $this->ledgerService->post(
            $reference,
            'loan.disbursement',
            $transaction,
            [
                [
                    'account_id' => $this->loanReceivableAccount($loan, $currency)->id,
                    'direction' => LedgerEntry::DIRECTION_DEBIT,
                    'amount_minor' => $amountMinor,
                    'memo' => 'Loan principal receivable created',
                ],
                [
                    'account_id' => $this->providerCashAccount($provider, 'disbursement', $currency)->id,
                    'direction' => LedgerEntry::DIRECTION_CREDIT,
                    'amount_minor' => $amountMinor,
                    'memo' => 'Cash disbursed through payment provider',
                ],
            ],
            null,
            $currency,
            [
                'loan_id' => $loan->id,
                'legacy_transaction_id' => $transaction->id,
                'loan_application_id' => $transaction->loan_application_id,
                'payment_provider' => strtolower($provider),
                'legacy_compatibility_path' => true,
            ]
        );

        $this->auditLogger->record('ledger.loan_disbursement.posted', null, $posted, [
            'loan_id' => $loan->id,
            'transaction_id' => $transaction->id,
            'amount_minor' => $amountMinor,
            'payment_provider' => strtolower($provider),
            'legacy_compatibility_path' => true,
        ]);

        return $posted;
    }

    public function postRepayment(
        Transaction $transaction,
        int|float $interestPaid,
        int|float $principalPaid,
        int|float $feesPaid = 0,
    ): ?LedgerTransaction {
        $reference = $this->ledgerReference('loan.repayment', $transaction);
        if (LedgerTransaction::where('reference', $reference)->exists()) {
            return null;
        }

        $amountMinor = $this->toMinorUnits($transaction->amount);
        $interestMinor = $this->toMinorUnits($interestPaid);
        $principalMinor = $this->toMinorUnits($principalPaid);
        $feesMinor = $this->toMinorUnits($feesPaid);
        $suspenseMinor = $amountMinor - $interestMinor - $principalMinor - $feesMinor;
        if ($suspenseMinor < 0) {
            throw new \InvalidArgumentException('Repayment ledger components exceed the collected amount.');
        }

        $provider = $this->paymentProvider($transaction);
        $currency = 'UGX';
        $entries = [[
            'account_id' => $this->providerCashAccount($provider, 'collection', $currency)->id,
            'direction' => LedgerEntry::DIRECTION_DEBIT,
            'amount_minor' => $amountMinor,
            'memo' => 'Cash collected through payment provider',
        ]];

        if ($principalMinor > 0) {
            $entries[] = [
                'account_id' => $this->loanReceivableAccount($transaction->loan, $currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $principalMinor,
                'memo' => 'Loan principal repaid',
            ];
        }
        if ($interestMinor > 0) {
            $entries[] = [
                'account_id' => $this->interestIncomeAccount($transaction->loan, $currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $interestMinor,
                'memo' => 'Interest income recognized on repayment',
            ];
        }
        if ($feesMinor > 0) {
            $entries[] = [
                'account_id' => $this->repaymentFeeAccount($transaction->loan, $currency)->id,
                'direction' => LedgerEntry::DIRECTION_CREDIT,
                'amount_minor' => $feesMinor,
                'memo' => $transaction->loan?->credit_offer_id
                    ? 'Financed fee receivable extinguished by customer repayment'
                    : 'Legacy cash allocated to disclosed credit fees pending accounting-policy recognition',
            ];
        }
        if ($suspenseMinor > 0) {
            $entries[] = [
                'account_id' => $this->account('liability.customer_repayment_suspense', 'Customer repayment suspense', 'liability', $currency)->id,
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
            $currency,
            [
                'loan_id' => $transaction->loan_id,
                'legacy_transaction_id' => $transaction->id,
                'payment_provider' => strtolower($provider),
                'principal_minor' => $principalMinor,
                'interest_minor' => $interestMinor,
                'fees_minor' => $feesMinor,
                'suspense_minor' => $suspenseMinor,
            ]
        );

        $this->auditLogger->record('ledger.loan_repayment.posted', null, $posted, [
            'loan_id' => $transaction->loan_id,
            'transaction_id' => $transaction->id,
            'amount_minor' => $amountMinor,
            'payment_provider' => strtolower($provider),
        ]);

        return $posted;
    }

    private function creditDisbursementComponents(CreditOffer $offer): array
    {
        $principalMinor = (int) $offer->principal_amount_minor;
        $cashMinor = (int) $offer->net_disbursement_minor;
        $feesMinor = (int) $offer->fees_minor;
        if ($principalMinor <= 0 || $cashMinor <= 0 || $feesMinor < 0) {
            throw new \InvalidArgumentException('Production credit disbursement amounts must use valid positive/non-negative integer minor units.');
        }

        if ($offer->fee_treatment === 'deducted') {
            if ($cashMinor + $feesMinor !== $principalMinor) {
                throw new \InvalidArgumentException('Deducted-fee disbursement must satisfy net cash plus deducted fees equals approved principal.');
            }

            return [$principalMinor, $cashMinor, $feesMinor, 0];
        }

        if ($offer->fee_treatment === 'financed') {
            if ($cashMinor !== $principalMinor) {
                throw new \InvalidArgumentException('Financed-fee disbursement must send the full approved principal in cash.');
            }

            return [$principalMinor, $cashMinor, 0, $feesMinor];
        }

        throw new \InvalidArgumentException('Unsupported production credit fee treatment.');
    }

    private function assertCreditPaymentMatchesOffer(MobileMoneyTransaction $mobileMoney, CreditOffer $offer, int $cashMinor): void
    {
        if ($mobileMoney->direction !== MobileMoneyTransaction::DIRECTION_DISBURSEMENT) {
            throw new \InvalidArgumentException('Credit-offer ledger posting requires a disbursement money movement.');
        }
        if ((int) $mobileMoney->credit_offer_id !== (int) $offer->id) {
            throw new \InvalidArgumentException('Money movement credit_offer_id does not match the ledger offer.');
        }
        if ((int) $mobileMoney->amount_minor !== $cashMinor) {
            throw new \InvalidArgumentException('Provider-confirmed disbursement amount does not match the offer net cash amount.');
        }
        if (strtoupper((string) $mobileMoney->currency) !== strtoupper((string) $offer->currency)) {
            throw new \InvalidArgumentException('Provider-confirmed disbursement currency does not match the offer currency.');
        }
    }

    private function repaymentFeeAccount(?Loan $loan, string $currency): LedgerAccount
    {
        if (! $loan) {
            throw new \InvalidArgumentException('Repayment fee posting requires a loan.');
        }
        if (! $loan->credit_offer_id) {
            return $this->creditFeeClearingAccount($loan, $currency);
        }

        $offer = CreditOffer::query()->find($loan->credit_offer_id);
        if (! $offer || $offer->fee_treatment !== 'financed') {
            throw new \InvalidArgumentException('Production repayment contains fees that are not backed by a financed-fee offer.');
        }

        return $this->creditFeeReceivableAccount($loan, $currency);
    }

    private function ledgerReference(string $eventType, Transaction $transaction): string
    {
        return $eventType.':'.$transaction->reference;
    }

    private function loanReceivableAccount(Loan $loan, string $currency): LedgerAccount
    {
        return $this->account('asset.loan_receivable.product_'.$loan->loan_product_id, 'Loan receivable product '.$loan->loan_product_id, 'asset', $currency);
    }

    private function creditFeeReceivableAccount(Loan $loan, string $currency): LedgerAccount
    {
        return $this->account('asset.credit_fee_receivable.product_'.$loan->loan_product_id, 'Credit fee receivable product '.$loan->loan_product_id, 'asset', $currency);
    }

    private function interestIncomeAccount(Loan $loan, string $currency): LedgerAccount
    {
        return $this->account('income.interest.product_'.$loan->loan_product_id, 'Interest income product '.$loan->loan_product_id, 'income', $currency);
    }

    private function creditFeeClearingAccount(Loan $loan, string $currency): LedgerAccount
    {
        return $this->account('liability.credit_fee_clearing.product_'.$loan->loan_product_id, 'Credit fee clearing product '.$loan->loan_product_id, 'liability', $currency);
    }

    private function providerCashAccount(string $provider, string $purpose, string $currency): LedgerAccount
    {
        $provider = strtolower($provider ?: 'unknown');

        return $this->account("cash.{$provider}.{$purpose}", ucfirst($provider).' '.$purpose.' cash', 'asset', $currency);
    }

    private function paymentProvider(Transaction $transaction): string
    {
        $provider = MobileMoneyTransaction::query()->where('transaction_id', $transaction->id)->latest('id')->value('provider');

        return ucfirst(strtolower((string) ($provider ?: $transaction->network ?: 'unknown')));
    }

    private function account(string $code, string $name, string $type, string $currency): LedgerAccount
    {
        $currency = strtoupper($currency);
        $existing = LedgerAccount::query()->where('code', $code)->first();
        if ($existing) {
            if (strtoupper((string) $existing->currency) !== $currency) {
                throw new \InvalidArgumentException("Ledger account {$code} is bound to {$existing->currency}; cross-currency reuse is not allowed.");
            }
            if (! $existing->is_active) {
                throw new \InvalidArgumentException("Ledger account {$code} is inactive.");
            }

            return $existing;
        }

        return LedgerAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'currency' => $currency,
            'is_active' => true,
        ]);
    }

    private function toMinorUnits(int|float|string $amount): int
    {
        $numeric = (float) $amount;
        $rounded = (int) round($numeric);
        if (abs($numeric - $rounded) > 0.000001) {
            throw new \InvalidArgumentException('Legacy monetary value contains fractional minor units and cannot be posted safely.');
        }

        return $rounded;
    }
}
