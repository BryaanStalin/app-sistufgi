<?php

namespace Database\Factories;

use App\Models\ServicioUps;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServicioUps> */
class ServicioUpsFactory extends Factory
{
    protected $model = ServicioUps::class;

    public function definition(): array
    {
        return [
            'ID_UPS' => fake()->unique()->numberBetween(100, 999999),
            'DESCRIPCION_UPS' => 'Atención '.fake()->unique()->word(),
            'TIPO_UPS' => 'P',
            'ACTIVO' => true,
        ];
    }
}
