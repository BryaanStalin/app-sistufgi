<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\LabPaciente;
use App\Models\PacienteMock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorioTmService
{
    public function obtenerOGenerar(Campania $campania, int $pacienteId, int $actorId): LabPaciente
    {
        return DB::transaction(function () use ($campania, $pacienteId, $actorId): LabPaciente {
            $campania = Campania::query()
                ->lockForUpdate()
                ->findOrFail($campania->id);
            $campania->assertEditable();
            if (! $campania->incluye_laboratorio || ! $campania->estaEnPeriodoAtencion()) {
                throw ValidationException::withMessages([
                    'campania' => 'El laboratorio solo registra durante los días de atención de la campaña.',
                ]);
            }
            PacienteMock::query()->findOrFail($pacienteId);
            $existente = LabPaciente::withTrashed()
                ->deCampania($campania->id)
                ->where('paciente_id', $pacienteId)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if ($existente) {
                if ($existente->trashed()) {
                    $existente->deleted_by = null;
                    $existente->updated_by = $actorId;
                    $existente->restore();
                }

                return $existente;
            }
            $campania->lab_ultimo_tm++;
            $campania->updated_by = $actorId;
            $campania->save();

            return LabPaciente::create([
                'campania_id' => $campania->id,
                'paciente_id' => $pacienteId,
                'tm_numero' => $campania->lab_ultimo_tm,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }, attempts: 3);
    }

    public function formatear(int $numero): string
    {
        return 'TM-'.str_pad((string) $numero, 3, '0', STR_PAD_LEFT);
    }
}
