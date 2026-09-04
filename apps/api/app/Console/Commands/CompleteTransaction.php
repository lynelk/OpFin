<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\LoanService;
use Illuminate\Console\Command;

class CompleteTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opfin:complete-transaction {--transactionId=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transaction = Transaction::find($this->option('transactionId'));
        if ($transaction->type == 'Repayment') {
            $schedules = $transaction->loan->schedules()->get();
            $interestPaidTotal = 0;
            $principalPaidTotal = 0;
            $remainingPayment = $transaction->amount;

            foreach ($schedules as $schedule) {
                if ($remainingPayment <= 0) break;

                $paymentBreakdown = $schedule->applyPayment($remainingPayment);
                $interestPaidTotal += $paymentBreakdown['interestPaid'];
                $principalPaidTotal += $paymentBreakdown['principalPaid'];
                $remainingPayment -= ($paymentBreakdown['interestPaid'] + $paymentBreakdown['principalPaid']);
            }

            $loanService = app(LoanService::class);
            $loanService->processCollection($transaction, $interestPaidTotal, $principalPaidTotal);
        }
    }
}
