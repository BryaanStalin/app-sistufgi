<?php

namespace Database\Factories;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\ServicioUps;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampaniaServicio> */
class CampaniaServicioFactory extends Factory
{
    protected $model = CampaniaServicio::class;

    public function definition(): array
    {
        return ['campania_id' => Campania::factory(), 'ups_id' => ServicioUps::factory(), 'fecha_servicio' => today(), 'cupos' => 10, 'cupo_base' => 10, 'cupo_personalizado' => false];
    }
}
