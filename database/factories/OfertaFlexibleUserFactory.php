<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class OfertaFlexibleUserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return ['nombre' => fake()->name(), 'user' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(), 'password' => 'AcceptedPass1', 'status' => 1];
    }
}
