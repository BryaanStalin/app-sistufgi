<?php

namespace Database\Factories;

use App\Models\Campania;
use App\Models\LabPaciente;
use App\Models\PacienteMock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LabPaciente> */
class LabPacienteFactory extends Factory
{
    protected $model = LabPaciente::class;

    public function definition(): array
    {
        return [
            'campania_id' => Campania::factory()->state(['incluye_laboratorio' => true]),
            'paciente_id' => PacienteMock::factory(),
            'tm_numero' => 1,
        ];
    }
}
