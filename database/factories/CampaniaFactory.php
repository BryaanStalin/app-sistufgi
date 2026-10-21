<?php

namespace Database\Factories;

use App\Models\Campania;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campania> */
class CampaniaFactory extends Factory
{
    protected $model = Campania::class;

    public function definition(): array
    {
        return ['nombre' => fake()->sentence(3), 'fecha_inicio' => today(), 'fecha_fin' => today(), 'asociar_triaje_admision' => true, 'mismos_servicios' => true, 'cerrada' => false];
    }
}
