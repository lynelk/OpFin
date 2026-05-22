<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\DemoConsent;
use App\Models\Institution;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InvestorDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::updateOrCreate(
            ['email' => 'demo-institution@opfin.test'],
            [
                'name' => 'OpFin Demo Institution',
                'address' => 'Kampala',
                'phone' => '256700000100',
            ],
        );

        $customer = User::updateOrCreate(
            ['phone' => '256700000001'],
            [
                'name' => 'Investor Demo Customer',
                'email' => 'customer.demo@opfin.test',
                'role' => User::ROLE_CUSTOMER,
                'institution_id' => $institution->id,
                'password' => Hash::make('password'),
                'national_id' => 'CM000000000001',
                'date_of_birth' => '1994-04-12',
                'nin_status' => 'VALID',
                'validated_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['phone' => '256700000099'],
            [
                'name' => 'Investor Demo Admin',
                'email' => 'admin.demo@opfin.test',
                'role' => User::ROLE_PLATFORM_ADMIN,
                'institution_id' => $institution->id,
                'password' => Hash::make('password'),
            ],
        );

        $product = LoanProduct::updateOrCreate(
            ['name' => 'Investor Demo Salary Advance'],
            [
                'type' => 'Cash',
                'institution_id' => $institution->id,
            ],
        );

        LoanProductTerm::updateOrCreate(
            ['loan_product_id' => $product->id, 'duration' => 30],
            [
                'interest_rate' => 10,
                'interest_type' => 'Flat',
                'interest_cycle' => 'Monthly',
                'repayment_frequency' => 'Monthly',
            ],
        );

        Account::updateOrCreate(
            ['name' => 'Airtel Disbursement'],
            ['balance' => 5000000],
        );

        Account::updateOrCreate(
            ['loan_product_id' => $product->id],
            ['name' => 'Investor Demo Salary Advance Portfolio', 'balance' => 0],
        );

        DemoConsent::updateOrCreate(
            ['user_id' => $customer->id, 'purpose' => DemoConsent::PURPOSE_CREDIT_PROCESSING],
            [
                'status' => DemoConsent::STATUS_REVOKED,
                'revoked_at' => now(),
                'metadata' => ['mock_integration' => true, 'seeded_demo_record' => true],
            ],
        );
    }
}
