<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\SmsService;

class TransactionSeeder extends Seeder
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $loans = Loan::all();

        // Ensure the Funding Account exists

        foreach ($loans as $loan) {
            // Create a disbursement transaction
            $disbursementTransaction = Transaction::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'type' => 'Disbursement',
                'amount' => $loan->amount,
                'phone' => '1234567890',
                'reference' => 'D-' . strtoupper(uniqid()),
                'external_reference' => null,
                'status' => 'Completed',
            ]);

            // Update Disbursement Account balance
            $disbursementAccount = Account::firstOrCreate(['name' => 'Disbursement']);
            $previousBalance = $disbursementAccount->balance;
            $disbursementAccount->balance += $loan->amount;
            $disbursementAccount->save();

            // Create journal entry for disbursement account
            JournalEntry::create([
                'account_id' => $disbursementAccount->id,
                'type' => 'Debit',
                'amount' => $loan->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $disbursementAccount->balance,
                'reference' => $disbursementTransaction->reference,
                'description' => 'Loan Disbursement',
            ]);

            // Debit the Funding Account
            $fundsAccount = Account::firstOrCreate(['name' => 'Funds']);
            $previousBalance = $fundsAccount->balance;
            $fundsAccount->balance -= $loan->amount;
            $fundsAccount->save();

            // Create journal entry for funding account
            JournalEntry::create([
                'account_id' => $fundsAccount->id,
                'type' => 'Credit',
                'amount' => $loan->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $fundsAccount->balance,
                'reference' => $disbursementTransaction->reference,
                'description' => 'Loan Disbursement',
            ]);

            // Queue SMS for disbursement
            $this->smsService->queueSms($loan->user->phone, 'Your loan has been disbursed.');

            // Create a repayment transaction
            $repaymentTransaction = Transaction::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'type' => 'Repayment',
                'amount' => $loan->repayment_amount,
                'phone' => '1234567890',
                'reference' => 'R-' . strtoupper(uniqid()),
                'external_reference' => null,
                'status' => 'Pending',
            ]);

            // Update Repayment Account balance
            $repaymentAccount = Account::firstOrCreate(['name' => 'Repayment']);
            $previousBalance = $repaymentAccount->balance;
            $repaymentAccount->balance += $loan->repayment_amount;
            $repaymentAccount->save();

            // Create journal entry for repayment account
            JournalEntry::create([
                'account_id' => $repaymentAccount->id,
                'type' => 'Credit',
                'amount' => $loan->repayment_amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $repaymentAccount->balance,
                'reference' => $repaymentTransaction->reference,
                'description' => 'Loan repayment',
            ]);

            // Calculate interest and principal portions of the repayment
            $interestPortion = ($loan->amount * $loan->interest_rate) / 100;
            $principalPortion = $loan->amount;

            // Update Interest Account balance
            $interestAccount = Account::firstOrCreate(['name' => 'Interest']);
            $previousBalance = $interestAccount->balance;
            $interestAccount->balance += $interestPortion;
            $interestAccount->save();

            // Create journal entry for interest account
            JournalEntry::create([
                'account_id' => $interestAccount->id,
                'type' => 'Debit',
                'amount' => $interestPortion,
                'previous_balance' => $previousBalance,
                'current_balance' => $interestAccount->balance,
                'reference' => $repaymentTransaction->reference,
                'description' => 'Interest portion of loan repayment',
            ]);

            // Update Principal Account balance
            $principalAccount = Account::firstOrCreate(['name' => 'Principal']);
            $previousBalance = $principalAccount->balance;
            $principalAccount->balance += $principalPortion;
            $principalAccount->save();

            // Create journal entry for principal account
            JournalEntry::create([
                'account_id' => $principalAccount->id,
                'type' => 'Debit',
                'amount' => $principalPortion,
                'previous_balance' => $previousBalance,
                'current_balance' => $principalAccount->balance,
                'reference' => $repaymentTransaction->reference,
                'description' => 'Principal portion of loan repayment',
            ]);

            // Queue SMS for repayment
            $this->smsService->queueSms($loan->user->phone, 'Your loan repayment has been received.');
        }
    }
}
