<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\LabAtencion;
use App\Models\LabPaciente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LaboratorioRegistroService
{
    public const HOJA_BLOQUEADA = 'No se puede eliminar la hoja de ruta: el paciente tiene un registro de laboratorio. Elimine primero el registro de laboratorio.';

    public function activas(): Builder
    {
        return LabAtencion::query()
            ->whereHas('labPaciente')
            ->whereHas('examenes', fn (Builder $query) => $query->whereColumn(
                'TB_LAB_ATENCION_EXAMENES.lab_paciente_id', 'TB_LAB_ATENCIONES.lab_paciente_id',
            ));
    }

    private function vinculadasAHoja(): Builder
    {
        return $this->activas()->where(function (Builder $query): void {
            $query->whereColumn('TB_LAB_ATENCIONES.hoja_ruta_id', 'TB_HOJAS_RUTA.id')
                ->orWhere(function (Builder $legado): void {
                    $legado->whereNull('TB_LAB_ATENCIONES.hoja_ruta_id')
                        ->whereColumn('TB_LAB_ATENCIONES.campania_id', 'TB_HOJAS_RUTA.campania_id')
                        ->whereColumn('TB_LAB_ATENCIONES.fecha_atencion', 'TB_HOJAS_RUTA.fecha_atencion')
                        ->whereHas('labPaciente', fn (Builder $paciente) => $paciente->whereColumn(
                            'TB_LAB_PACIENTES.paciente_id', 'TB_HOJAS_RUTA.paciente_id',
                        ));
                });
        });
    }

    /** @param list<int> $ids
     * @return list<int>
     */
    public function hojasBloqueadas(array $ids): array
    {
        return HojaRuta::query()
            ->whereIn('id', $ids)
            ->whereExists($this->vinculadasAHoja()->selectRaw('1')->toBase())
            ->pluck('id')
            ->all();
    }

    public function assertHojaEliminable(HojaRuta $hoja): void
    {
        if ($this->hojasBloqueadas([$hoja->id]) !== []) {
            throw ValidationException::withMessages(['hoja_id' => self::HOJA_BLOQUEADA]);
        }
    }

    public function eliminar(Campania $campania, int $pacienteId, int $atencionId, int $autor): void
    {
        $lab = LabPaciente::query()
            ->deCampania($campania->id)
            ->where('paciente_id', $pacienteId)
            ->lockForUpdate()
            ->firstOrFail();
        $atencion = $lab->atenciones()
            ->where('campania_id', $campania->id)
            ->where('fecha_atencion', today('America/Lima'))
            ->lockForUpdate()
            ->findOrFail($atencionId);
        $ids = $atencion->examenes()->pluck('campania_servicio_id');
        CampaniaServicio::query()->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $examenes = $atencion->examenes()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($examenes->contains('estado', 'T')) {
            throw ValidationException::withMessages([
                'atencion_id' => 'No se puede eliminar: ya hay muestras tomadas para este paciente.',
            ]);
        }
        foreach ($examenes as $examen) {
            $examen->deleted_by = $autor;
            $examen->delete();
        }
        $atencion->deleted_by = $autor;
        $atencion->delete();
    }
}
