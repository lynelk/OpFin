<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanProductTerm;
use App\Models\LoanProduct;

class LoanProductTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cashLoanProduct = LoanProduct::where('name', 'Cash Loan')->first();
        $assetLoanProduct = LoanProduct::where('name', 'Asset Loan')->first();

        LoanProductTerm::create([
            'loan_product_id' => $cashLoanProduct->id,
            'interest_rate' => 12,
            'duration' => 30,
            'interest_type' => 'Amortization',
            'repayment_frequency' => 'Weekly',
        ]);

        LoanProductTerm::create([
            'loan_product_id' => $assetLoanProduct->id,
            'interest_rate' => 12,
            'duration' => 30,
            'repayment_frequency' => 'Monthly',
        ]);
    }
}
