<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $accounts = [
            ['name' => 'Airtel Disbursement', 'balance' => 46103],
            ['name' => 'Airtel Collection', 'balance' => 325447],

            ['name' => 'MTN Disbursement', 'balance' => 0],
            ['name' => 'MTN Collection', 'balance' => 149788],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
