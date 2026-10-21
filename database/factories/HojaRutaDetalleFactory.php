<?php

namespace Database\Factories;

use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HojaRutaDetalle> */
class HojaRutaDetalleFactory extends Factory
{
    protected $model = HojaRutaDetalle::class;

    public function definition(): array
    {
        return ['hoja_ruta_id' => HojaRuta::factory(), 'campania_servicio_id' => CampaniaServicio::factory()];
    }
}
