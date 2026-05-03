<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\LoanSchedule;

class LoanScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $loans = Loan::all();

        foreach ($loans as $loan) {
            $principal = $loan->amount;
            $interest = ($loan->amount * $loan->interest_rate) / 100;
            $totalAmount = $principal + $interest;

            LoanSchedule::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'principal' => $principal,
                'interest' => $interest,
                'balance' => $totalAmount,
                'due_date' => $loan->repayment_start_date
            ]);
        }
    }
}
