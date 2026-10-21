<?php

namespace Database\Factories;

use App\Models\Campania;
use App\Models\CampaniaUsuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampaniaUsuario> */
class CampaniaUsuarioFactory extends Factory
{
    protected $model = CampaniaUsuario::class;

    public function definition(): array
    {
        return ['campania_id' => Campania::factory(), 'usuario_id' => OfertaFlexibleUserFactory::new(), 'tipo' => 'M'];
    }
}
