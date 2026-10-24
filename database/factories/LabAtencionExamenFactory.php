<?php

namespace Database\Factories;

use App\Models\CampaniaServicio;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\ServicioUps;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LabAtencionExamen> */
class LabAtencionExamenFactory extends Factory
{
    protected $model = LabAtencionExamen::class;

    public function definition(): array
    {
        return [
            'atencion_id' => LabAtencion::factory(),
            'lab_paciente_id' => fn (array $data): int => LabAtencion::findOrFail($data['atencion_id'])->lab_paciente_id,
            'campania_servicio_id' => fn (array $data): int => CampaniaServicio::factory()->create([
                'campania_id' => LabAtencion::findOrFail($data['atencion_id'])->campania_id,
                'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L']),
            ])->id,
            'ups_id' => fn (array $data): int => CampaniaServicio::findOrFail($data['campania_servicio_id'])->ups_id,
            'estado' => 'P',
        ];
    }
}
