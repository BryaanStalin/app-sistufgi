<?php

namespace Database\Factories;

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampaniaEnlace> */
class CampaniaEnlaceFactory extends Factory
{
    protected $model = CampaniaEnlace::class;

    public function definition(): array
    {
        return [
            'campania_id' => Campania::factory(),
            'medico_usuario_id' => OfertaFlexibleUserFactory::new(),
            'admision_usuario_id' => OfertaFlexibleUserFactory::new(),
        ];
    }
}
