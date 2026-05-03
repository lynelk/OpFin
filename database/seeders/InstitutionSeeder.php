<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Institution::create([
            'name' => 'Shell Uganda',
            'address' => 'Kampala, Uganda',
            'email' => 'contact@shelluganda.com',
            'phone' => '256-700-000-001',
        ]);

        Institution::create([
            'name' => 'Cafe Javas',
            'address' => 'Kampala, Uganda',
            'email' => 'info@cafejavas.com',
            'phone' => '256-700-000-002',
        ]);
    }
}
