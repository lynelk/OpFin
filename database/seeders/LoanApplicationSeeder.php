<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;

class LoanApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::all();
        $loanProducts = LoanProduct::all();
        $loanProductTerms = LoanProductTerm::all();
        $institutions = Institution::all();

        for ($i = 0; $i < 10; $i++) {
            LoanApplication::create([
                'user_id' => $users->random()->id,
                'loan_product_id' => $loanProducts->random()->id,
                'loan_product_term_id' => $loanProductTerms->random()->id,
                'institution_id' => $institutions->random()->id, // Assuming institution_id 1 exists
                'amount' => rand(500000, 3000000),
                'status' => 'Pending',
                'reason' => 'Personal Loan',
                'disbursed_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'cancelled_at' => null,
            ]);
        }
    }
}
