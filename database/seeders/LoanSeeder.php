<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Loan;
use App\Models\User;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\Institution;
use App\Models\LoanApplication;

class LoanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::first();
        $loanProduct = LoanProduct::first();
        $loanProductTerm = LoanProductTerm::first();
        $institution = Institution::first();
        $loanApplication = LoanApplication::first();
        $duration = 12;

        Loan::create([
            'user_id' => $user->id,
            'loan_product_id' => $loanProduct->id,
            'loan_product_term_id' => $loanProductTerm->id,
            'institution_id' => $institution->id,
            'loan_application_id' => $loanApplication->id,
            'amount' => 10000,
            'status' => 'Cleared',
            'reason' => 'Business Expansion',
            'disbursed_at' => now(),
            'duration' => $duration,
            'repayment_amount' => 10500,
            'repayment_start_date' => now()->addDay(),
        ]);
    }
}
