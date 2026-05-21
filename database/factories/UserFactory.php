<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('2567########'),
            'email' => fake()->unique()->safeEmail(),
            'role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('password'),
        ];
    }
}
