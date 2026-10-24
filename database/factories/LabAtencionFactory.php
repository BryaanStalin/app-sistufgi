<?php

namespace Database\Factories;

use App\Models\LabAtencion;
use App\Models\LabPaciente;
use App\Models\ServicioUps;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LabAtencion> */
class LabAtencionFactory extends Factory
{
    protected $model = LabAtencion::class;

    public function definition(): array
    {
        return [
            'lab_paciente_id' => LabPaciente::factory(),
            'campania_id' => fn (array $data): int => LabPaciente::findOrFail($data['lab_paciente_id'])->campania_id,
            'fecha_atencion' => today('America/Lima'),
            'fua' => fake()->numerify('##########'),
            'ups_id_solicita' => ServicioUps::factory(),
        ];
    }
}
