<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InstitutionSeeder::class,
            UserSeeder::class,
            LoanProductSeeder::class,
            LoanProductTermSeeder::class,
            LoanApplicationSeeder::class,
            LoanSeeder::class,
            LoanScheduleSeeder::class,
            AccountSeeder::class,
            TransactionSeeder::class,
            FloatTopupSeeder::class,
        ]);
    }
}
