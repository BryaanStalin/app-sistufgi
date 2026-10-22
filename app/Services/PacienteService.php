<?php

namespace App\Services;

use App\Models\PacienteMock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PacienteService
{
    public const TIPOS_DOCUMENTO = ['CNV', 'DNI', 'CE', 'S/ DOCUMENTO', 'DIE', 'PASS'];

    /**
     * @return array{
     *     id: int,
     *     tipo_doc: ?string,
     *     dni: ?string,
     *     nombres_apellidos: string,
     *     edad: int,
     *     sexo: ?string
     * }|null
     */
    public function buscar(string $tipo, ?string $numero): ?array
    {
        if ($tipo === 'S/ DOCUMENTO') {
            return null;
        }
        $paciente = PacienteMock::query()
            ->where('tipo_doc', $tipo)
            ->where('dni', $numero)
            ->orderBy('id')
            ->first();

        return $paciente ? $this->datos($paciente) : null;
    }

    /** @return array{id: int, tipo_doc: ?string, dni: ?string, nombres_apellidos: string, edad: int, sexo: ?string} */
    public function buscarId(int $id): array
    {
        return $this->datos(PacienteMock::query()->findOrFail($id));
    }

    /**
     * @param array{
     *     tipo_doc: string,
     *     dni?: ?string,
     *     nombres_apellidos: string,
     *     fecha_nacimiento: string,
     *     sexo: string,
     *     telefono?: ?string
     * } $data
     * @return array{id: int, tipo_doc: ?string, dni: ?string, nombres_apellidos: string, edad: int, sexo: ?string}
     */
    public function registrar(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $sinDocumento = $data['tipo_doc'] === 'S/ DOCUMENTO';
            if (! $sinDocumento) {
                $existente = PacienteMock::query()
                    ->where('tipo_doc', $data['tipo_doc'])
                    ->where('dni', $data['dni'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                if ($existente) {
                    return $this->datos($existente);
                }
            }
            /** Se genera antes del INSERT: esta fuente de pacientes solo admite inserciones. */
            $numero = $sinDocumento ? 'SD-'.strtoupper(bin2hex(random_bytes(12))) : $data['dni'];
            $paciente = PacienteMock::create([
                'tipo_doc' => $data['tipo_doc'],
                'dni' => $numero,
                'nombres_apellidos' => $data['nombres_apellidos'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'sexo' => $data['sexo'],
                'telefono' => $data['telefono'] ?? null,
            ]);

            return $this->datos($paciente);
        }, attempts: 3);
    }

    public function calcularEdad(string $fechaNacimiento): int
    {
        return max(
            0,
            (int) CarbonImmutable::parse($fechaNacimiento, 'America/Lima')->diffInYears(
                CarbonImmutable::today('America/Lima'),
            ),
        );
    }

    /** @return array{id: int, tipo_doc: ?string, dni: ?string, nombres_apellidos: string, edad: int, sexo: ?string} */
    private function datos(PacienteMock $paciente): array
    {
        return [
            'id' => $paciente->id,
            'tipo_doc' => $paciente->tipo_doc,
            'dni' => $paciente->dni,
            'nombres_apellidos' => $paciente->nombres_apellidos,
            'edad' => $this->calcularEdad($paciente->fecha_nacimiento->toDateString()),
            'sexo' => $paciente->sexo,
        ];
    }
}
