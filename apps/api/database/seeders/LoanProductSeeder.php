<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanProduct;

class LoanProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        LoanProduct::create([
            'name' => 'Cash Loan',
            'type' => 'Cash',
            'status' => 'Active',
        ]);

        LoanProduct::create([
            'name' => 'Asset Loan',
            'type' => 'Asset',
            'status' => 'Active',
        ]);
    }
}
