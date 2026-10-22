<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\ServicioUps;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TriajeService
{
    public function __construct(protected PacienteService $pacientes, protected CampaniaService $campanias)
    {
    }

    /** @return Collection<int, Campania> */
    public function campanias(User $medico): Collection
    {
        $this->assertMedico($medico);

        return Campania::query()
            ->activas()
            ->whereHas(
                'asignaciones',
                fn ($query) => $query->where('usuario_id', $medico->id)->where('tipo', 'M'),
            )
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'cerrada']);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function operar(User $medico, array $data): array
    {
        return DB::transaction(function () use ($medico, $data): array {
            $this->assertMedico($medico);
            /** El mismo orden de bloqueo que Administración impide cierres o retiros durante la operación. */
            $campania = Campania::query()
                ->whereHas(
                    'asignaciones',
                    fn ($query) => $query->where('usuario_id', $medico->id)->where('tipo', 'M'),
                )
                ->lockForUpdate()
                ->findOrFail($data['campania_id']);
            $hoy = CarbonImmutable::today('America/Lima');
            if ($campania->estado !== 'Activa') {
                throw ValidationException::withMessages([
                    'campania_id' => 'La campaña ya no está activa. Solo se puede registrar durante sus días de atención.',
                ]);
            }
            if (!$campania->estaEnPeriodoAtencion()) {
                throw ValidationException::withMessages([
                    'campania_id' => 'La campaña inicia el '.$campania->fecha_inicio->format('d/m/Y').'.',
                ]);
            }
            $paciente = match ($data['accion']) {
                'buscar' => $this->pacientes->buscar($data['tipo_doc'], $data['dni'] ?? null),
                'registrar' => $this->pacientes->registrar($data),
                default => isset($data['paciente_id'])
                    ? $this->pacientes->buscarId((int) $data['paciente_id'])
                    : null,
            };
            if ($data['accion'] === 'grabar') {
                $this->guardarHoja(
                    $campania,
                    $medico,
                    $paciente['id'],
                    $hoy,
                    array_map('intval', $data['servicios']),
                );
            }

            return $this->contexto($campania, $paciente, $hoy);
        }, attempts: 3);
    }

    /** @param list<int> $seleccionados */
    private function guardarHoja(
        Campania $campania,
        User $medico,
        int $pacienteId,
        CarbonImmutable $hoy,
        array $seleccionados,
    ): void {
        $hoja = $campania
            ->hojas()
            ->where('paciente_id', $pacienteId)
            ->where('fecha_atencion', $hoy)
            ->lockForUpdate()
            ->first();
        $anteriores = $hoja ? $hoja
            ->detalles()
            ->lockForUpdate()
            ->get() : collect();
        $originales = $anteriores->pluck('campania_servicio_id')->all();
        $afectados = array_values(array_unique([...$originales, ...$seleccionados]));
        sort($afectados);
        $filas = CampaniaServicio::query()
            ->where('campania_id', $campania->id)
            ->where('fecha_servicio', $hoy)
            ->whereIn('id', $afectados)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $validos = $filas->filter(
            fn (CampaniaServicio $fila): bool => in_array($fila->id, $seleccionados, true),
        );
        $ups = ServicioUps::query()
            ->whereIn('id', $validos->pluck('ups_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $activos = $validos->filter(
            fn (CampaniaServicio $fila): bool => (bool) $ups->get($fila->ups_id)?->ACTIVO,
        );
        if (
            count($seleccionados) !== count(array_unique($seleccionados)) ||
            $activos->count() !== count($seleccionados)
        ) {
            throw ValidationException::withMessages([
                'servicios' => 'Seleccione servicios activos de esta campaña disponibles para hoy, sin repetir.',
            ]);
        }
        $usados = $this->campanias->usage($afectados);
        foreach ($activos as $fila) {
            $reservaPropia = in_array($fila->id, $originales, true) ? 1 : 0;
            if (($usados[$fila->id] ?? 0) - $reservaPropia + 1 > $fila->cupos) {
                throw ValidationException::withMessages([
                    'servicios' => 'Se agotó el cupo de '.
                        $ups->get($fila->ups_id)->DESCRIPCION_UPS.
                        '. Actualice los cupos y seleccione otro servicio.',
                ]);
            }
        }
        $hoja ??= new HojaRuta([
            'campania_id' => $campania->id,
            'paciente_id' => $pacienteId,
            'fecha_atencion' => $hoy,
            'medico_usuario_id' => $medico->id,
            'created_by' => $medico->id,
        ]);
        $hoja->updated_by = $medico->id;
        $hoja->updated_at = now('America/Lima');
        $hoja->save();
        foreach ($anteriores as $detalle) {
            if (!in_array($detalle->campania_servicio_id, $seleccionados, true)) {
                $detalle->deleted_by = $medico->id;
                $detalle->delete();
            }
        }
        foreach (array_diff($seleccionados, $originales) as $servicioId) {
            HojaRutaDetalle::create([
                'hoja_ruta_id' => $hoja->id,
                'campania_servicio_id' => $servicioId,
                'created_by' => $medico->id,
                'updated_by' => $medico->id,
            ]);
        }
    }

    /** @param array<string, int|string|null>|null $paciente
     * @return array<string, mixed>
     */
    private function contexto(Campania $campania, ?array $paciente, CarbonImmutable $hoy): array
    {
        $hoja = $paciente
            ? $campania
                ->hojas()
                ->where('paciente_id', $paciente['id'])
                ->where('fecha_atencion', $hoy)
                ->with('detalles')
                ->first()
            : null;
        $seleccionados = $hoja?->detalles->pluck('campania_servicio_id')->all() ?? [];
        $filas = $campania
            ->servicios()
            ->where('fecha_servicio', $hoy)
            ->whereHas('servicioUps', fn ($query) => $query->where('ACTIVO', true))
            ->with('servicioUps')
            ->orderBy('id')
            ->get();
        $usados = $this->campanias->usage($filas->pluck('id')->all());

        return [
            'campania' => ['id' => $campania->id, 'nombre' => $campania->nombre],
            'paciente' => $paciente,
            'hoja' => $hoja ? ['id' => $hoja->id, 'servicios' => $seleccionados] : null,
            'servicios' => $filas
                ->map(
                    fn (CampaniaServicio $fila): array => [
                        'id' => $fila->id,
                        'nombre' => $fila->servicioUps->DESCRIPCION_UPS,
                        'tipo' => $fila->servicioUps->tipo_etiqueta,
                        'disponibles' => max(0, $fila->cupos - ($usados[$fila->id] ?? 0)),
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    private function assertMedico(User $medico): void
    {
        abort_unless(
            OfertaFlexibleAccess::hasRole($medico, 'Médico Triaje') ||
                OfertaFlexibleAccess::hasRole($medico, 'Administrador'),
            403,
            'Solo el Médico Triaje o el Administrador pueden realizar esta operación.',
        );
    }
}
