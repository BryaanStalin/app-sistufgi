<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\LabPaciente;
use App\Models\ServicioUps;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorioAsignacionService
{
    public function __construct(protected LaboratorioRegistroService $registros) {}

    public function campanias(User $usuario): EloquentBuilder
    {
        abort_unless(LaboratorioTomaMuestraService::puedeAcceder($usuario), 403);

        return Campania::query()
            ->where('incluye_laboratorio', true)
            ->where('cerrada', false)
            ->when(! OfertaFlexibleAccess::hasRole($usuario, 'Administrador'), fn (EloquentBuilder $query) => $query
                ->whereHas('asignaciones', fn (EloquentBuilder $asignacion) => $asignacion
                    ->where('usuario_id', $usuario->id)
                    ->where('tipo', 'L')));
    }

    public function campania(User $usuario, int $id, bool $bloquear = false): Campania
    {
        return $this->campanias($usuario)
            ->when($bloquear, fn (EloquentBuilder $query) => $query->lockForUpdate())
            ->findOrFail($id);
    }

    /** @return Collection<int, ServicioUps> */
    public function columnas(Campania $campania): Collection
    {
        return ServicioUps::query()
            ->where('TIPO_UPS', 'L')
            ->where('ENVIA_EESS', true)
            ->whereHas('campaniaServicios', fn (EloquentBuilder $query) => $query->where('campania_id', $campania->id))
            ->orderBy('ID_UPS')
            ->orderBy('id')
            ->get(['id', 'DESCRIPCION_UPS']);
    }

    /** @param array<string, mixed> $filtros
     * @param  Collection<int, ServicioUps>  $columnas
     */
    public function consulta(Campania $campania, array $filtros, Collection $columnas): Builder
    {
        $query = DB::table('TB_LAB_PACIENTES as laboratorio')
            ->join('TB_PACIENTES_MOCK as paciente', 'paciente.id', '=', 'laboratorio.paciente_id')
            ->join('TB_CAMPANIAS as campania', 'campania.id', '=', 'laboratorio.campania_id')
            ->where('laboratorio.campania_id', $campania->id)
            ->whereNull('laboratorio.deleted_at')
            ->whereIn('laboratorio.id', $this->registros->activas()
                ->where('campania_id', $campania->id)
                ->select('lab_paciente_id'))
            ->whereNull('campania.deleted_at')
            ->select([
                'laboratorio.id', 'laboratorio.tm_numero', 'laboratorio.eess_renipress',
                'paciente.tipo_doc', 'paciente.dni', 'paciente.nombres', 'paciente.apellidos', 'paciente.telefono',
            ]);
        if ($columnas->isNotEmpty()) {
            $realizados = $this->realizados($campania, $columnas);
            $query->leftJoinSub($realizados, 'realizados', 'realizados.lab_paciente_id', '=', 'laboratorio.id');
            foreach ($columnas as $columna) {
                $alias = 'examen_'.(int) $columna->id;
                $query->selectRaw('COALESCE(realizados.'.$alias.', 0) AS '.$alias);
            }
        }
        $estado = $filtros['asignacion'] ?? 'todos';
        if ($estado === 'asignados') {
            $query->whereNotNull('laboratorio.eess_renipress');
        } elseif ($estado === 'sin_asignar') {
            $query->whereNull('laboratorio.eess_renipress');
        }
        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $this->buscar($query, $busqueda);
        }

        return $query->orderBy('laboratorio.tm_numero')->orderBy('laboratorio.id');
    }

    private function realizados(Campania $campania, Collection $columnas): Builder
    {
        $query = DB::table('TB_LAB_ATENCION_EXAMENES as examen')
            ->join('TB_LAB_ATENCIONES as atencion', 'atencion.id', '=', 'examen.atencion_id')
            ->join('TB_CAMPANIA_SERVICIOS as servicio', 'servicio.id', '=', 'examen.campania_servicio_id')
            ->where('atencion.campania_id', $campania->id)
            ->where('servicio.campania_id', $campania->id)
            ->whereColumn('atencion.lab_paciente_id', 'examen.lab_paciente_id')
            ->whereColumn('servicio.ups_id', 'examen.ups_id')
            ->whereColumn('servicio.fecha_servicio', 'atencion.fecha_atencion')
            ->whereNull('examen.deleted_at')
            ->whereNull('atencion.deleted_at')
            ->whereNull('servicio.deleted_at')
            ->where('examen.estado', 'T')
            ->whereIn('examen.ups_id', $columnas->pluck('id'))
            ->groupBy('examen.lab_paciente_id')
            ->select('examen.lab_paciente_id');
        foreach ($columnas as $columna) {
            $query->selectRaw(
                'MAX(CASE WHEN examen.ups_id = ? THEN 1 ELSE 0 END) AS examen_'.(int) $columna->id,
                [(int) $columna->id],
            );
        }

        return $query;
    }

    private function buscar(Builder $query, string $busqueda): void
    {
        $palabras = preg_split('/\s+/u', $busqueda, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $numero = preg_match('/^(?:TM[-\s]*)?([0-9]+)$/i', $busqueda, $codigo)
            ? ltrim($codigo[1], '0') : null;
        $query->where(function (Builder $query) use ($busqueda, $palabras, $numero): void {
            $this->contiene($query, 'paciente.dni', $busqueda);
            $query->orWhere(function (Builder $nombre) use ($palabras): void {
                foreach ($palabras as $palabra) {
                    $nombre->where(function (Builder $parte) use ($palabra): void {
                        $this->contiene($parte, 'paciente.nombres', $palabra);
                        $this->contiene($parte, 'paciente.apellidos', $palabra, 'or');
                    });
                }
            });
            if ($numero !== null && strlen($numero) <= 10 && (int) $numero <= 2147483647) {
                $query->orWhere('laboratorio.tm_numero', (int) $numero);
            }
        });
    }

    private function contiene(Builder $query, string $columna, string $texto, string $booleano = 'and'): void
    {
        $literal = str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $texto);
        $query->whereRaw($columna." LIKE ? ESCAPE '!'", ['%'.$literal.'%'], $booleano);
    }

    /** @param list<int|string> $ids */
    public function asignar(User $usuario, int $campaniaId, array $ids, string $renipress): int
    {
        return DB::transaction(function () use ($usuario, $campaniaId, $ids, $renipress): int {
            $campania = $this->campania($usuario, $campaniaId, true);
            if (! in_array($renipress, array_column(config('oferta_flexible.laboratorio.eess'), 'renipress'), true)) {
                throw ValidationException::withMessages(['renipress' => 'Seleccione uno de los EESS configurados.']);
            }
            $pacientes = LabPaciente::query()
                ->deCampania($campania->id)
                ->whereIn('id', $this->registros->activas()
                    ->where('campania_id', $campania->id)
                    ->select('lab_paciente_id'))
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($pacientes->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'pacientes' => 'Todos los pacientes seleccionados deben pertenecer a esta campaña.',
                ]);
            }
            $ahora = now('America/Lima');
            foreach ($pacientes as $paciente) {
                $paciente->fill([
                    'eess_renipress' => $renipress,
                    'eess_asignado_at' => $ahora,
                    'eess_asignado_by' => $usuario->id,
                    'updated_by' => $usuario->id,
                ])->save();
            }

            return $pacientes->count();
        }, attempts: 3);
    }
}
