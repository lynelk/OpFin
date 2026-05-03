<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\FloatTopup;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\Otp;
use App\Models\SmsMessage;
use App\Models\Transaction;
use Illuminate\Console\Command;
use App\Services\AirtelService;
use Illuminate\Support\Facades\DB;

class CleanUp extends Command
{
    protected $signature = 'opfin:cleanup';
    protected $description = 'Perform system cleanup tasks';

    public function handle()
    {
        try {

            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            /*
            |--------------------------------------------------------------------------
            | Tables with Eloquent models
            |--------------------------------------------------------------------------
            */
            JournalEntry::truncate();
            Account::whereNull('loan_product_id')->delete();
            // LoanProductTerm::truncate();
            // LoanProduct::truncate();
            LoanApplication::truncate();
            LoanSchedule::truncate();
            Loan::truncate();
            FloatTopup::truncate();
            Otp::truncate();
            SmsMessage::truncate();
            Transaction::truncate();

            /*
|--------------------------------------------------------------------------
| Tables WITHOUT models
|--------------------------------------------------------------------------
*/
            DB::table('personal_access_tokens')->truncate();
            DB::table('failed_jobs')->truncate();
            DB::table('cache')->truncate();
            DB::table('cache_locks')->truncate();
            DB::table('jobs')->truncate();
            DB::table('job_batches')->truncate();
            DB::table('password_reset_tokens')->truncate();
            DB::table('sessions')->truncate();

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $this->info('System cleanup completed successfully.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
