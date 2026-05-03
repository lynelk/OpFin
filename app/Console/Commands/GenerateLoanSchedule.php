<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateLoanSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-loan-schedule {loanId}';

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
        // Validate the loan ID argument
        if (!$this->argument('loanId')) {
            $this->error('Loan ID is required.');
            return;
        }
        $loanId = $this->argument('loanId');
        $loan = \App\Models\Loan::find($loanId);
        if (!$loan) {
            $this->error('Loan not found.');
            return;
        }
        // first delete any existing schedules
        $loan->schedules()->delete();
        $loan->createLoanSchedule();
    }
}
