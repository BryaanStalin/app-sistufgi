<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\LabPaciente;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorioAdmisionService
{
    public const DOCUMENTOS = ['DNI', 'CE', 'PASS', 'DIE', 'CNV', 'S/ DOCUMENTO'];

    public const PACIENTE_NO_ENCONTRADO = 'No se encontró al paciente. Debe ser registrado primero en triaje.';

    public const SIN_HOJA = 'El paciente no tiene hoja de ruta de hoy en esta campaña. Debe pasar primero por triaje.';

    public const REPETIDO = 'YA SE HA REGISTRADO ESTE EXAMEN PARA EL PACIENTE EN UNA ATENCIÓN PREVIA';

    public const HORA_LIMITE = 'Por la hora el paciente ya no debe tomarse esta prueba, para evitar alteraciones en el resultado';

    public function __construct(
        protected PacienteService $pacientes,
        protected LaboratorioStockService $stock,
        protected LaboratorioTmService $tm,
        protected LaboratorioRegistroService $registros,
    ) {}

    public static function puedeAcceder(?User $usuario): bool
    {
        return OfertaFlexibleAccess::hasRole($usuario, 'Administrador') ||
            OfertaFlexibleAccess::hasRole($usuario, 'Admisión Laboratorio');
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
                    ->where('usuario_id', $usuario->id)->where('tipo', 'AL'),
            ));
    }

    public function campania(User $usuario, int $id, bool $bloquear = false): Campania
    {
        return $this->campanias($usuario)
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->findOrFail($id);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function operar(User $usuario, array $data): array
    {
        return DB::transaction(function () use ($usuario, $data): array {
            $campania = $this->campania($usuario, (int) $data['campania_id'], true);
            $paciente = $data['accion'] === 'buscar'
                ? $this->pacientes->buscarDocumentoLocal($data['tipo_doc'], $data['dni'])
                : $this->pacientes->buscarId((int) $data['paciente_id']);
            if (! $paciente) {
                $this->fail('dni', self::PACIENTE_NO_ENCONTRADO);
            }
            if (! in_array($paciente['tipo_doc'], self::DOCUMENTOS, true)) {
                $this->fail('paciente_id', 'Seleccione un tipo de documento válido.');
            }
            $hoja = $this->hoja($campania, $paciente['id'], true);
            if (! $hoja) {
                $this->fail('paciente_id', self::SIN_HOJA);
            }
            if ($data['accion'] !== 'buscar') {
                $this->validarExamenes($campania, $paciente['id'], $data, $data['accion'] === 'grabar');
                if ($data['accion'] === 'grabar') {
                    $this->guardar($campania, $paciente['id'], $data, $usuario->id, $hoja);
                }
            }

            return $this->contexto($campania, $paciente);
        }, attempts: 3);
    }

    /** @param array{campania_id: int, paciente_id: int, atencion_id: int} $data
     * @return array<string, mixed>
     */
    public function eliminar(User $usuario, array $data): array
    {
        return DB::transaction(function () use ($usuario, $data): array {
            $campania = $this->campania($usuario, (int) $data['campania_id'], true);
            $this->registros->eliminar(
                $campania, (int) $data['paciente_id'], (int) $data['atencion_id'], $usuario->id,
            );

            return $this->contexto($campania) + ['message' => 'Registro de laboratorio eliminado.'];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function contexto(Campania $campania, ?array $paciente = null): array
    {
        $lab = $paciente ? LabPaciente::query()->deCampania($campania->id)
            ->where('paciente_id', $paciente['id'])->first() : null;
        $atencion = $lab ? $lab->atenciones()
            ->where('fecha_atencion', today('America/Lima'))
            ->first() : null;
        $historial = $lab ? LabAtencionExamen::query()
            ->where('lab_paciente_id', $lab->id)
            ->whereHas('atencion')
            ->with(['atencion', 'servicioUps'])
            ->orderByDesc('id')->get() : collect();
        $hoja = $paciente ? $this->hoja($campania, $paciente['id']) : null;
        if ($paciente) {
            $paciente['telefono'] = PacienteMock::query()
                ->findOrFail($paciente['id'])
                ->telefono;
        }
        $examenes = $campania->servicios()->laboratorio()
            ->where('fecha_servicio', today('America/Lima'))
            ->with('servicioUps')
            ->get();
        $stock = collect($this->stock->delDia($campania))->keyBy('campania_servicio_id');
        $catalogo = ServicioUps::query()
            ->where('TIPO_UPS', 'L')
            ->whereHas(
                'campaniaServicios', fn (Builder $q) => $q->where('campania_id', $campania->id),
            )
            ->orderBy('ID_UPS')
            ->get();

        $solicitantes = $paciente && $hoja ? $this->serviciosSolicitantes($campania, $hoja) : collect();

        return [
            'paciente' => $paciente,
            'tm' => $lab ? $this->tm->formatear($lab->tm_numero) : null,
            'atencion' => $atencion ? [
                'id' => $atencion->id, 'fua' => $atencion->fua, 'ups_id_solicita' => $atencion->ups_id_solicita,
            ] : null,
            'servicios' => $solicitantes
                ->map(fn ($fila): array => [
                    'id' => $fila->ups_id,
                    'nombre' => $fila->servicioUps->DESCRIPCION_UPS,
                ])
                ->values()
                ->all(),
            'examenes' => $catalogo->map(function ($ups) use ($examenes, $stock): array {
                $fila = $examenes->firstWhere('ups_id', $ups->id);

                return [
                    'id' => $fila?->id,
                    'ups_id' => $ups->id,
                    'nombre' => $ups->DESCRIPCION_UPS,
                    'disponible' => $fila && ($stock->get($fila->id)['disponible'] ?? false),
                    'sin_configuracion' => $fila === null,
                    'activo' => (bool) $ups->ACTIVO,
                ];
            })->all(),
            'historial' => $historial->map(fn ($fila): array => [
                'id' => $fila->id, 'campania_servicio_id' => $fila->campania_servicio_id,
                'nombre' => $fila->servicioUps->DESCRIPCION_UPS, 'estado' => $fila->estado,
                'etiqueta' => LabAtencionExamen::ESTADOS[$fila->estado],
                'fecha' => $fila->atencion->fecha_atencion->format('d/m/Y'),
                'editable' => $fila->estado === 'P' && $fila->atencion_id === $atencion?->id,
            ])->all(),
            'stock' => $stock->values()->all(),
        ];
    }

    private function hoja(Campania $campania, int $pacienteId, bool $bloquear = false): ?HojaRuta
    {
        return $campania->hojas()->where('paciente_id', $pacienteId)
            ->where('fecha_atencion', today('America/Lima'))
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    private function serviciosSolicitantes(Campania $campania, HojaRuta $hoja): Collection
    {
        return $campania->servicios()->triaje()
            ->where('fecha_servicio', today('America/Lima'))
            ->whereHas('servicioUps', fn (Builder $query) => $query->where('ACTIVO', true))
            ->whereHas(
                'detalles', fn (Builder $detalle) => $detalle->where('hoja_ruta_id', $hoja->id),
            )
            ->with('servicioUps')
            ->orderBy('id')
            ->get();
    }

    /** @param array<string, mixed> $data */
    private function validarExamenes(Campania $campania, int $pacienteId, array $data, bool $bloquear): void
    {
        $nuevos = array_map('intval', $data['examenes']);
        $eliminar = array_map('intval', $data['eliminar'] ?? []);
        $lab = LabPaciente::withTrashed()
            ->deCampania($campania->id)
            ->where('paciente_id', $pacienteId)
            ->first();
        $retirosQuery = LabAtencionExamen::query()->whereIn('id', $eliminar)
            ->where('lab_paciente_id', $lab?->id ?? 0)
            ->where('estado', 'P')
            ->whereHas('atencion', fn (Builder $q) => $q->where('fecha_atencion', today('America/Lima')));
        $retiros = (clone $retirosQuery)->get();
        if ($retiros->count() !== count($eliminar)) {
            $this->fail('eliminar', 'Solo se pueden quitar exámenes Pendientes de la atención de hoy.');
        }
        $afectados = array_unique([...$nuevos, ...$retiros->pluck('campania_servicio_id')->all()]);
        $filas = CampaniaServicio::query()->where('campania_id', $campania->id)
            ->whereIn('id', $afectados)->where('fecha_servicio', today('America/Lima'))
            ->laboratorio()->orderBy('id')
            ->when($bloquear, fn (Builder $q) => $q->lockForUpdate())
            ->get()
            ->keyBy('id');
        if ($bloquear) {
            $retiros = $retirosQuery->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($retiros->count() !== count($eliminar)) {
                $this->fail('eliminar', 'Solo se pueden quitar exámenes Pendientes de la atención de hoy.');
            }
        }
        $ups = ServicioUps::query()
            ->whereIn('id', $filas->pluck('ups_id'))
            ->orderBy('id')
            ->when($bloquear, fn (Builder $q) => $q->lockForUpdate())
            ->get()
            ->keyBy('id');
        $ocupados = LabAtencionExamen::query()->where('lab_paciente_id', $lab?->id ?? 0)
            ->where('estado', '<>', 'N')
            ->whereNotIn('id', $eliminar)
            ->pluck('ups_id')
            ->all();
        $stock = collect($this->stock->delDia($campania))->keyBy('campania_servicio_id');
        foreach ($nuevos as $id) {
            $fila = $filas->get($id);
            $examen = $fila ? $ups->get($fila->ups_id) : null;
            if (! $examen || ! $examen->ACTIVO) {
                $this->fail('examenes', 'Seleccione un examen activo de esta campaña con insumos configurados para hoy.');
            }
            if (in_array($fila->ups_id, $ocupados, true)) {
                $this->fail('examenes', self::REPETIDO, $examen->DESCRIPCION_UPS);
            }
            $ocupados[] = $fila->ups_id;
            $this->assertHora($examen);
            $devueltos = $retiros->where('campania_servicio_id', $id)->count();
            if (($stock->get($id)['quedan'] ?? 0) + $devueltos < 1) {
                $this->fail('examenes', 'No hay insumos de '.$examen->DESCRIPCION_UPS, $examen->DESCRIPCION_UPS);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function guardar(
        Campania $campania,
        int $pacienteId,
        array $data,
        int $actorId,
        HojaRuta $hoja,
    ): void {
        if ($data['examenes'] === [] && ($data['eliminar'] ?? []) === []) {
            $this->fail('examenes', 'Agregue al menos un examen nuevo o quite un examen Pendiente de hoy.');
        }
        if (! $this->serviciosSolicitantes($campania, $hoja)->contains('ups_id', (int) $data['ups_id_solicita'])) {
            $this->fail('ups_id_solicita', 'Seleccione un servicio que solicita válido para el paciente y la fecha de hoy.');
        }
        $lab = $this->tm->obtenerOGenerar($campania, $pacienteId, $actorId);
        $atencion = $lab->atenciones()
            ->where('fecha_atencion', today('America/Lima'))
            ->lockForUpdate()
            ->first();
        $atencion ??= new LabAtencion([
            'lab_paciente_id' => $lab->id, 'campania_id' => $campania->id,
            'fecha_atencion' => today('America/Lima'), 'created_by' => $actorId,
        ]);
        $atencion->fill([
            'fua' => $data['fua'], 'ups_id_solicita' => $data['ups_id_solicita'],
            'hoja_ruta_id' => $hoja->id, 'updated_by' => $actorId,
        ])->save();
        foreach ($atencion->examenes()
            ->whereIn('id', $data['eliminar'] ?? [])
            ->get() as $examen) {
            $examen->deleted_by = $actorId;
            $examen->delete();
        }
        $filas = $campania->servicios()->whereIn('id', $data['examenes'])
            ->with('servicioUps')
            ->get()
            ->keyBy('id');
        foreach ($data['examenes'] as $id) {
            $examen = $filas->get($id)->servicioUps;
            $this->assertHora($examen);
            LabAtencionExamen::create([
                'atencion_id' => $atencion->id, 'lab_paciente_id' => $lab->id,
                'campania_servicio_id' => $id, 'ups_id' => $filas->get($id)->ups_id,
                'estado' => 'P', 'created_by' => $actorId, 'updated_by' => $actorId,
            ]);
        }
        $paciente = PacienteMock::query()
            ->lockForUpdate()
            ->findOrFail($pacienteId);
        $paciente->telefono = $data['telefono'];
        $paciente->save();
    }

    private function assertHora(ServicioUps $examen): void
    {
        if ($examen->HORA_LIMITE && now('America/Lima')->format('H:i:s') >= substr($examen->HORA_LIMITE, 0, 8)) {
            $this->fail('examenes', self::HORA_LIMITE, $examen->DESCRIPCION_UPS);
        }
    }

    private function fail(string $campo, string $mensaje, ?string $examen = null): never
    {
        throw ValidationException::withMessages(
            [$campo => $mensaje] + ($examen ? ['examen_fallido' => $examen] : []),
        );
    }
}
