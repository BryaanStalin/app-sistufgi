<?php

namespace Database\Factories;

use App\Models\Campania;
use App\Models\HojaRuta;
use App\Models\PacienteMock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HojaRuta> */
class HojaRutaFactory extends Factory
{
    protected $model = HojaRuta::class;

    public function definition(): array
    {
        return [
            'campania_id' => Campania::factory(),
            'paciente_id' => PacienteMock::factory(),
            'medico_usuario_id' => OfertaFlexibleUserFactory::new(),
            'fecha_atencion' => today(),
        ];
    }
}
