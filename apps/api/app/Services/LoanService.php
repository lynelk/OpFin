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
use LogicException;

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
     * Legacy compatibility origination. Production must use the governed
     * decision -> offer -> CPay finality -> exact schedule path instead.
     */
    public function createLoanFromApplication(LoanApplication $loanApplication): ?Loan
    {
        if (app()->environment('production') && ! config('opfin.credit.legacy_origination_enabled', false)) {
            throw new LogicException('Legacy loan origination is disabled in production. Use the production credit-offer workflow.');
        }

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
        } catch (Exception $e) {
            Log::error('Loan creation failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Process a successful legacy payment event once.
     */
    public function processSuccessfulTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction->refresh();

            if ($transaction->type === 'Disbursement') {
                if ($transaction->loan_id || Loan::where('loan_application_id', $transaction->loan_application_id)->exists()) {
                    return;
                }

                if (app()->environment('production') && ! config('opfin.credit.legacy_origination_enabled', false)) {
                    throw new LogicException('Legacy disbursement finality cannot originate a production loan.');
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

                if (abs((float) $remainingPayment) > 0.000001) {
                    throw new LogicException('Legacy repayment allocation did not consume the collected amount exactly.');
                }

                $this->processCollection($transaction, $interestPaidTotal, $principalPaidTotal);
            }
        });
    }

    /**
     * Legacy account/journal compatibility posting.
     */
    public function processDisbursement(Transaction $transaction, Loan $loan): void
    {
        try {
            $disbursementAccountName = match ($transaction->network) {
                'AIRTEL' => 'Airtel Disbursement',
                'MTN' => 'MTN Disbursement',
                default => throw new Exception('Unsupported network: '.$transaction->network),
            };

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
        } catch (Exception $e) {
            Log::error('Disbursement processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Legacy account/journal compatibility posting.
     */
    public function processCollection(Transaction $transaction, float $interestPaid, float $principalPaid): void
    {
        try {
            $collectionAccountName = match ($transaction->network) {
                'AIRTEL' => 'Airtel Collection',
                'MTN' => 'MTN Collection',
                default => throw new Exception('Unsupported network: '.$transaction->network),
            };

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
        } catch (Exception $e) {
            Log::error('Collection processing failed: '.$e->getMessage());
            throw $e;
        }
    }

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

    protected function updateAccountBalance(
        Account $account,
        float $amount,
        string $type,
        string $reference,
        string $description
    ): void {
        $rounded = (int) round($amount);
        if (abs($amount - $rounded) > 0.000001 || $rounded <= 0) {
            throw new LogicException('Legacy account posting requires a positive integer minor-unit amount.');
        }

        $previousBalance = $account->balance;
        $account->balance = $type === 'Debit'
            ? $account->balance - $rounded
            : $account->balance + $rounded;
        $account->save();

        JournalEntry::create([
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $rounded,
            'previous_balance' => $previousBalance,
            'current_balance' => $account->balance,
            'reference' => $reference,
            'description' => $description,
        ]);
    }

    protected function sendDisbursementNotification(Loan $loan): void
    {
        $this->smsService->queueSms($loan->user->phone, $this->prepareDisbursementMessage($loan));
    }

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

    protected function sendCollectionNotification(Transaction $transaction): void
    {
        $this->smsService->queueSms($transaction->loan->user->phone, $this->prepareCollectionMessage($transaction));
    }

    protected function prepareCollectionMessage(Transaction $transaction): string
    {
        $outstandingBalance = $transaction->loan->outstanding_balance;
        $message = sprintf(
            'Hello %s, thank you for your loan repayment of %s.',
            $transaction->loan->user->name,
            number_format($transaction->amount)
        );

        if ($outstandingBalance > 0) {
            return $message.sprintf(' Your outstanding balance is %s.', number_format($outstandingBalance));
        }

        $transaction->loan->update(['status' => 'Cleared']);

        return $message.' Your loan has been fully settled. We appreciate your business!';
    }
}
