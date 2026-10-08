<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, create a platform administrator
        User::create([
            'name' => 'Super Admin',
            'phone' => '+256776911458',
            'email' => 'admin@opfin.com',
            'role' => User::ROLE_PLATFORM_ADMIN,
            // Development only. Set a known password with opfin:admin:reset-password when needed.
            'password' => Str::password(32),
            'remember_token' => Str::random(10),
        ]);
        // Then, create the 'Member' users
        User::factory()->count(9)->create();
    }
}
