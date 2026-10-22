<?php

namespace Database\Factories;

use App\Models\HojaRuta;
use App\Models\HojaRutaImpresion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HojaRutaImpresion> */
class HojaRutaImpresionFactory extends Factory
{
    protected $model = HojaRutaImpresion::class;

    public function definition(): array
    {
        return [
            'hoja_ruta_id' => HojaRuta::factory(),
            'impreso_por' => OfertaFlexibleUserFactory::new(),
            'impreso_at' => now(),
            'created_at' => now(),
        ];
    }
}
