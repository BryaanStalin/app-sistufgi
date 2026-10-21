<?php

namespace Database\Factories;

use App\Models\PacienteMock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PacienteMock> */
class PacienteMockFactory extends Factory
{
    protected $model = PacienteMock::class;

    public function definition(): array
    {
        return ['dni' => fake()->unique()->numerify('########'), 'tipo_doc' => 'DNI', 'nombres_apellidos' => fake()->name(), 'fecha_nacimiento' => '1980-01-01', 'sexo' => 'F'];
    }
}
