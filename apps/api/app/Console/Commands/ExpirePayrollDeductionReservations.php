<?php

namespace App\Console\Commands;

use App\Services\PayrollDeduction\PayrollDeductionService;
use Illuminate\Console\Command;

class ExpirePayrollDeductionReservations extends Command
{
    protected $signature = 'payroll-deductions:expire-reservations';

    protected $description = 'Expire payroll deduction reservations that have passed their controlled reservation window.';

    public function handle(PayrollDeductionService $payroll): int
    {
        $count = $payroll->expireReservations();
        $this->info("Expired {$count} payroll deduction reservation(s).");

        return self::SUCCESS;
    }
}
