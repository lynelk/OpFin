<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanService
{
    protected $smsService;

    public function __construct(
        SmsService $smsService,
        private readonly ProductionLoanLedgerService $productionLoanLedgerService
    ) {
        $this->smsService = $smsService;
    }

    /**
     * Create a new loan from an approved application.
     */
    public function createLoanFromApplication(LoanApplication $loanApplication): ?Loan
    {
        try {
            return DB::transaction(function () use ($loanApplication) {
                $existingLoan = Loan::where('loan_application_id', $loanApplication->id)->first();
                if ($existingLoan) {
                    return $existingLoan;
                }

                $loanAmount = $loanApplication->amount;
                $interestRate = $loanApplication->loanProductTerm->interest_rate / 100;
                $duration = $loanApplication->loanProductTerm->duration;
                $repaymentFrequency = $loanApplication->loanProductTerm->repayment_frequency;
                $numberOfInstallments = Loan::getInstallments($duration, $repaymentFrequency);
                $interestType = $loanApplication->loanProductTerm->interest_type;
                $interestCycle = $loanApplication->loanProductTerm->interest_cycle;

                $loan = Loan::create([
                    'user_id' => $loanApplication->user_id,
                    'loan_product_id' => $loanApplication->loan_product_id,
                    'loan_product_term_id' => $loanApplication->loan_product_term_id,
                    'institution_id' => $loanApplication->institution_id,
                    'loan_application_id' => $loanApplication->id,
                    'amount' => $loanAmount,
                    'status' => 'Disbursed',
                    'reason' => $loanApplication->reason,
                    'disbursed_at' => now(),
                    'duration' => $duration,
                    'repayment_amount' => Loan::getRepaymentAmount(
                        $interestRate,
                        $loanAmount,
                        $interestType,
                        $numberOfInstallments,
                        $interestCycle,
                        $duration
                    ),
                    'repayment_start_date' => Loan::getRepaymentStartDate($repaymentFrequency),
                ]);

                if ($transaction = Transaction::where('loan_application_id', $loanApplication->id)->first()) {
                    $transaction->update(['loan_id' => $loan->id]);
                    $this->processDisbursement($transaction, $loan);
                }

                return $loan;
            });
        } catch (\Exception $e) {
            Log::error('Loan creation failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Process a successful payment event once.
     */
    public function processSuccessfulTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->refresh();

            if ($transaction->type === 'Disbursement') {
                if ($transaction->loan_id || Loan::where('loan_application_id', $transaction->loan_application_id)->exists()) {
                    return;
                }

                $transaction->loanApplication->update([
                    'status' => 'Disbursed',
                    'disbursed_at' => now(),
                ]);
                $this->createLoanFromApplication($transaction->loanApplication);

                return;
            }

            if ($transaction->type === 'Repayment') {
                if (JournalEntry::where('reference', $transaction->reference)->exists()) {
                    return;
                }

                $schedules = $transaction->loan->schedules()->get();
                $interestPaidTotal = 0;
                $principalPaidTotal = 0;
                $remainingPayment = $transaction->amount;

                foreach ($schedules as $schedule) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    $paymentBreakdown = $schedule->applyPayment($remainingPayment);
                    $interestPaidTotal += $paymentBreakdown['interestPaid'];
                    $principalPaidTotal += $paymentBreakdown['principalPaid'];
                    $remainingPayment -= ($paymentBreakdown['interestPaid'] + $paymentBreakdown['principalPaid']);
                }

                $this->processCollection($transaction, $interestPaidTotal, $principalPaidTotal);
            }
        });
    }

    /**
     * Process all disbursement activities.
     */
    public function processDisbursement(Transaction $transaction, Loan $loan): void
    {
        try {
            if ($transaction->network === 'AIRTEL') {
                $disbursementAccountName = 'Airtel Disbursement';
            } elseif ($transaction->network === 'MTN') {
                $disbursementAccountName = 'MTN Disbursement';
            } else {
                throw new Exception('Unsupported network: '.$transaction->network);
            }

            $disbursementAccount = Account::where('name', $disbursementAccountName)->first();
            if (! $disbursementAccount) {
                throw new Exception('Disbursement account not found');
            }
            if ($disbursementAccount->balance < $transaction->amount) {
                throw new Exception('Insufficient funds in disbursement account');
            }

            $this->updateAccountBalance(
                $disbursementAccount,
                $transaction->amount,
                'Debit',
                $transaction->reference,
                'Loan Disbursement'
            );

            $loanProductAccount = $this->loanProductPrincipalAccount($loan->loan_product_id);
            $this->updateAccountBalance(
                $loanProductAccount,
                $transaction->amount,
                'Credit',
                $transaction->reference,
                'Loan Disbursement'
            );

            $this->productionLoanLedgerService->postDisbursement($transaction, $loan);
            $this->sendDisbursementNotification($loan);
        } catch (\Exception $e) {
            Log::error('Disbursement processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Process all collection (repayment) activities.
     */
    public function processCollection(Transaction $transaction, float $interestPaid, float $principalPaid): void
    {
        try {
            if ($transaction->network === 'AIRTEL') {
                $collectionAccountName = 'Airtel Collection';
            } elseif ($transaction->network === 'MTN') {
                $collectionAccountName = 'MTN Collection';
            } else {
                throw new Exception('Unsupported network: '.$transaction->network);
            }

            $collectionAccount = Account::where('name', $collectionAccountName)->first();
            if (! $collectionAccount) {
                throw new Exception('Collection account not found');
            }

            $this->updateAccountBalance(
                $collectionAccount,
                $transaction->amount,
                'Credit',
                $transaction->reference,
                'Loan Repayment'
            );

            if ($interestPaid > 0) {
                $interestAccount = Account::where('name', 'Interest Income')
                    ->where('loan_product_id', $transaction->loan->loan_product_id)
                    ->first();
                if (! $interestAccount) {
                    throw new Exception('Interest Income account not found for loan product ID: '.$transaction->loan->loan_product_id);
                }

                $this->updateAccountBalance(
                    $interestAccount,
                    $interestPaid,
                    'Credit',
                    $transaction->reference,
                    'Interest Repayment'
                );
            }

            if ($principalPaid > 0) {
                $loanProductAccount = $this->loanProductPrincipalAccount($transaction->loan->loan_product_id);
                if ($loanProductAccount->balance < $principalPaid) {
                    throw new Exception('Insufficient funds in Loan Product account for loan product ID: '.$transaction->loan->loan_product_id);
                }

                $this->updateAccountBalance(
                    $loanProductAccount,
                    $principalPaid,
                    'Debit',
                    $transaction->reference,
                    'Principal Repayment'
                );
            }

            $this->productionLoanLedgerService->postRepayment($transaction, $interestPaid, $principalPaid);
            $this->sendCollectionNotification($transaction);
        } catch (\Exception $e) {
            Log::error('Collection processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve the principal balance account for a loan product.
     *
     * The legacy accounts table has no explicit account-role column yet, so
     * interest income must be excluded to avoid non-deterministic `first()`
     * selection when multiple accounts share the same loan_product_id.
     */
    private function loanProductPrincipalAccount(int $loanProductId): Account
    {
        $account = Account::where('loan_product_id', $loanProductId)
            ->where('name', '!=', 'Interest Income')
            ->orderBy('id')
            ->first();

        if (! $account) {
            throw new Exception('Loan Product account not found for loan product ID: '.$loanProductId);
        }

        return $account;
    }

    /**
     * Update account balance and create journal entry.
     */
    protected function updateAccountBalance(
        Account $account,
        float $amount,
        string $type,
        string $reference,
        string $description
    ): void {
        $previousBalance = $account->balance;

        $account->balance = $type === 'Debit'
            ? $account->balance - $amount
            : $account->balance + $amount;

        $account->save();

        JournalEntry::create([
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'previous_balance' => $previousBalance,
            'current_balance' => $account->balance,
            'reference' => $reference,
            'description' => $description,
        ]);
    }

    /**
     * Prepare and send disbursement notification.
     */
    protected function sendDisbursementNotification(Loan $loan): void
    {
        $message = $this->prepareDisbursementMessage($loan);
        $this->smsService->queueSms($loan->user->phone, $message);
    }

    /**
     * Prepare disbursement SMS message.
     */
    protected function prepareDisbursementMessage(Loan $loan): string
    {
        return sprintf(
            'Hello %s, your loan of %s has been disbursed. Repayment amount: %s due on %s. Thank you for choosing us!',
            $loan->user->name,
            number_format($loan->amount),
            number_format($loan->repayment_amount),
            $loan->repayment_start_date->format('d M Y')
        );
    }

    /**
     * Prepare and send collection notification.
     */
    protected function sendCollectionNotification(Transaction $transaction): void
    {
        $message = $this->prepareCollectionMessage($transaction);
        $this->smsService->queueSms($transaction->loan->user->phone, $message);
    }

    /**
     * Prepare collection SMS message.
     */
    protected function prepareCollectionMessage(Transaction $transaction): string
    {
        $outstandingBalance = $transaction->loan->outstanding_balance;
        $hasOutstandingBalance = $outstandingBalance > 0;

        $message = sprintf(
            'Hello %s, thank you for your loan repayment of %s.',
            $transaction->loan->user->name,
            number_format($transaction->amount)
        );

        if ($hasOutstandingBalance) {
            $message .= sprintf(
                ' Your outstanding balance is %s.',
                number_format($outstandingBalance),
            );
        } else {
            $message .= ' Your loan has been fully settled. We appreciate your business!';
            $transaction->loan->update(['status' => 'Cleared']);
        }

        return $message;
    }
}
