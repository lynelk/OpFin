<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FloatTopup;

class FloatTopupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FloatTopup::create([
            'amount' => 5000000, // Example amount
            'status' => 'Pending',
        ]);
    }
}
