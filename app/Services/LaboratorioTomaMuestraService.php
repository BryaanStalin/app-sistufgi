<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\LabAtencionExamen;
use App\Models\LabPaciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorioTomaMuestraService
{
    public const NO_ENCONTRADO = 'El paciente no tiene registro de laboratorio activo en esta campaña.';

    public function __construct(
        protected LaboratorioStockService $stock,
        protected LaboratorioTmService $tm,
        protected PacienteService $pacientes,
        protected LaboratorioRegistroService $registros,
    ) {}

    public static function puedeAcceder(?User $usuario): bool
    {
        return OfertaFlexibleAccess::hasRole($usuario, 'Administrador') ||
            OfertaFlexibleAccess::hasRole($usuario, 'Laboratorio');
    }

    public function campanias(User $usuario): Builder
    {
        abort_unless(self::puedeAcceder($usuario), 403);

        return Campania::query()
            ->activas()
            ->where('incluye_laboratorio', true)
            ->where('fecha_inicio', '<=', today('America/Lima'))
            ->when(! OfertaFlexibleAccess::hasRole($usuario, 'Administrador'), fn (Builder $query) => $query->whereHas(
                'asignaciones', fn (Builder $asignacion) => $asignacion
                    ->where('usuario_id', $usuario->id)
                    ->where('tipo', 'L'),
            ));
    }

    public function campania(User $usuario, int $id, bool $bloquear = false): Campania
    {
        return $this->campanias($usuario)
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->findOrFail($id);
    }

    private function pacientesVisibles(User $usuario, Campania $campania): Builder
    {
        return LabPaciente::query()
            ->deCampania($campania->id)
            ->whereIn('id', $this->registros->activas()
                ->where('campania_id', $campania->id)
                ->select('lab_paciente_id'))
            ->whereHas('paciente', fn (Builder $query) => $query->whereIn(
                'tipo_doc', LaboratorioAdmisionService::DOCUMENTOS,
            ));
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function operar(User $usuario, array $data): array
    {
        return DB::transaction(function () use ($usuario, $data): array {
            $guardar = $data['accion'] === 'grabar';
            $campania = $this->campania($usuario, (int) $data['campania_id'], $guardar);
            $query = $this->pacientesVisibles($usuario, $campania);
            if ($guardar) {
                $query->whereKey($data['lab_paciente_id']);
            } else {
                $query->whereHas('paciente', fn (Builder $paciente) => $paciente
                    ->where('tipo_doc', $data['tipo_doc'])
                    ->where('dni', $data['dni']));
            }
            $lab = $query->first();
            abort_unless($lab, 404, self::NO_ENCONTRADO);
            $advertencias = $guardar ? $this->guardar($campania, $lab, $data['estados'], $usuario->id) : [];

            return $this->contexto($campania, $lab) + ['advertencias' => $advertencias];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function contexto(Campania $campania, ?LabPaciente $lab = null): array
    {
        $paciente = $lab ? $this->pacientes->buscarId($lab->paciente_id) : null;
        if ($paciente) {
            $paciente['telefono'] = $lab->paciente->telefono;
        }
        $examenes = $lab ? $this->examenes($campania, $lab)
            ->with(['atencion', 'servicioUps'])
            ->orderByDesc('atencion_id')
            ->orderBy('id')
            ->get() : collect();

        return [
            'lab_paciente_id' => $lab?->id,
            'paciente' => $paciente,
            'tm' => $lab ? $this->tm->formatear($lab->tm_numero) : null,
            'examenes' => $examenes->map(fn (LabAtencionExamen $fila): array => [
                'id' => $fila->id,
                'campania_servicio_id' => $fila->campania_servicio_id,
                'nombre' => $fila->servicioUps->DESCRIPCION_UPS,
                'estado' => $fila->estado,
                'etiqueta' => LabAtencionExamen::ESTADOS[$fila->estado],
                'fecha' => $fila->atencion->fecha_atencion->format('d/m/Y'),
                'editable' => $fila->atencion->fecha_atencion->isSameDay(today('America/Lima')),
                'hora_superada' => $this->horaSuperada($fila),
            ])->all(),
            'stock' => $this->stock->delDia($campania),
        ];
    }

    private function examenes(Campania $campania, LabPaciente $lab): Builder
    {
        return $lab->examenes()
            ->whereHas('atencion', fn (Builder $query) => $query
                ->where('campania_id', $campania->id)
                ->where('lab_paciente_id', $lab->id))
            ->whereHas('campaniaServicio', fn (Builder $query) => $query
                ->where('campania_id', $campania->id)
                ->laboratorio())
            ->getQuery();
    }

    /** @param list<array{id: int, estado: string, estado_original: string}> $estados
     * @return list<array{examen: string, mensaje: string}>
     */
    private function guardar(Campania $campania, LabPaciente $lab, array $estados, int $autor): array
    {
        $ids = array_column($estados, 'id');
        $query = $this->examenes($campania, $lab)
            ->whereIn('id', $ids)
            ->whereHas('atencion', fn (Builder $query) => $query->where('fecha_atencion', today('America/Lima')));
        $serviciosIds = (clone $query)->pluck('campania_servicio_id');
        CampaniaServicio::query()
            ->whereIn('id', $serviciosIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $filas = $query->with('servicioUps')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($filas->count() !== count($ids)) {
            $this->fail('Solo se pueden modificar los exámenes de hoy del paciente seleccionado.');
        }
        $cambios = collect($estados)->keyBy('id');
        foreach ($filas as $fila) {
            if ($fila->estado !== $cambios[$fila->id]['estado_original']) {
                $this->fail('Otro usuario modificó un examen. Busque nuevamente al paciente antes de guardar.');
            }
        }
        $this->validarRepetidos($campania, $lab, $cambios);
        $this->validarInsumos($campania, $filas, $cambios);
        $advertencias = [];
        foreach ($filas->sortBy(fn ($fila) => $cambios[$fila->id]['estado'] === 'N' ? 0 : 1) as $fila) {
            $nuevo = $cambios[$fila->id]['estado'];
            if ($nuevo === $fila->estado) {
                continue;
            }
            if ($nuevo === 'T' && $this->horaSuperada($fila)) {
                $advertencias[] = [
                    'examen' => $fila->servicioUps->DESCRIPCION_UPS,
                    'mensaje' => LaboratorioAdmisionService::HORA_LIMITE,
                ];
            }
            $fila->fill([
                'estado' => $nuevo,
                'tomado_by' => $autor,
                'tomado_at' => now('America/Lima'),
                'updated_by' => $autor,
            ])->save();
        }

        return $advertencias;
    }

    private function validarRepetidos(Campania $campania, LabPaciente $lab, Collection $cambios): void
    {
        $ocupados = [];
        foreach ($this->examenes($campania, $lab)->get() as $fila) {
            $estado = $cambios->get($fila->id)['estado'] ?? $fila->estado;
            if ($estado === 'N') {
                continue;
            }
            if (isset($ocupados[$fila->ups_id])) {
                $this->fail(LaboratorioAdmisionService::REPETIDO);
            }
            $ocupados[$fila->ups_id] = true;
        }
    }

    private function validarInsumos(Campania $campania, Collection $filas, Collection $cambios): void
    {
        $stock = collect($this->stock->delDia($campania))->keyBy('campania_servicio_id');
        foreach ($filas->groupBy('campania_servicio_id') as $id => $grupo) {
            $delta = 0;
            foreach ($grupo as $fila) {
                $delta += (int) ($cambios[$fila->id]['estado'] !== 'N') - (int) ($fila->estado !== 'N');
            }
            if ($delta > 0 && ($stock->get($id)['quedan'] ?? 0) < $delta) {
                $this->fail('No hay insumos de '.$grupo->first()->servicioUps->DESCRIPCION_UPS);
            }
        }
    }

    private function horaSuperada(LabAtencionExamen $fila): bool
    {
        $limite = $fila->servicioUps->HORA_LIMITE;

        return $limite !== null && now('America/Lima')->format('H:i:s') >= substr($limite, 0, 8);
    }

    private function fail(string $mensaje): never
    {
        throw ValidationException::withMessages(['estados' => $mensaje]);
    }
}
