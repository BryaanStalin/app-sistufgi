<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRutaDetalle;
use App\Models\ServicioUps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaniaService
{
    /** @param array<string, mixed> $data */
    public function save(?Campania $campania, array $data, int $actorId): Campania
    {
        return DB::transaction(function () use ($campania, $data, $actorId): Campania {
            $campania = $campania
                ? Campania::query()
                    ->lockForUpdate()
                    ->findOrFail($campania->id)
                : new Campania;
            $campania->assertEditable();
            $anteriores = $campania->exists ? $campania
                ->servicios()
                ->lockForUpdate()
                ->get() : collect();
            $tieneHojas = $campania->exists && $campania
                ->hojas()
                ->withTrashed()
                ->exists();
            if ($tieneHojas && $campania->mismos_servicios !== (bool) $data['mismos_servicios']) {
                $this->fail('No se puede cambiar la modalidad de servicios cuando hay hojas registradas.');
            }
            $inicio = CarbonImmutable::parse($data['fecha_inicio'], 'America/Lima')->startOfDay();
            $fin = CarbonImmutable::parse($data['fecha_fin'], 'America/Lima')->startOfDay();
            if ($fin->lt($inicio)) {
                $this->fail('La fecha fin debe ser igual o posterior a la fecha inicio.');
            }
            if (
                $tieneHojas &&
                $campania
                    ->hojas()
                    ->withTrashed()
                    ->where(function ($query) use ($inicio, $fin): void {
                        $query->where('fecha_atencion', '<', $inicio)->orWhere('fecha_atencion', '>', $fin);
                    })
                    ->exists()
            ) {
                $this->fail('El rango no puede excluir días con hojas registradas.');
            }
            if (
                $campania->exists &&
                ! (bool) $data['asociar_triaje_admision'] &&
                $campania->enlaces()->exists()
            ) {
                $this->fail('Quite los enlaces antes de desactivar la asociación de Triaje y Admisión.');
            }
            $filas = $this->prepareRows($data, $inicio, $fin, $anteriores);
            $historicos = $anteriores->keyBy(
                fn (CampaniaServicio $fila): string => $this->key(
                    $fila->fecha_servicio->toDateString(),
                    $fila->ups_id,
                ),
            );
            $usados = $this->usage($anteriores->pluck('id')->all());
            $conHistorial = HojaRutaDetalle::withTrashed()
                ->whereIn('campania_servicio_id', $anteriores->pluck('id'))
                ->distinct()
                ->pluck('campania_servicio_id')
                ->all();
            foreach ($anteriores as $anterior) {
                $key = $this->key($anterior->fecha_servicio->toDateString(), $anterior->ups_id);
                if (! isset($filas[$key]) && in_array($anterior->id, $conHistorial, true)) {
                    $this->fail('No se puede retirar un servicio o día con hojas registradas.');
                }
                if (isset($filas[$key]) && $filas[$key]['cupos'] < ($usados[$anterior->id] ?? 0)) {
                    $this->fail('El cupo no puede ser menor que la cantidad de atenciones utilizadas.');
                }
            }
            $upsIds = array_unique(array_column($filas, 'ups_id'));
            $activos = ServicioUps::query()
                ->whereIn('id', $upsIds)
                ->where('ACTIVO', 1)
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            foreach ($filas as $key => $fila) {
                if (! in_array($fila['ups_id'], $activos, true) && ! $historicos->has($key)) {
                    $this->fail('Solo se pueden añadir servicios UPSS activos.');
                }
            }
            $campania->fill(
                collect($data)
                    ->only(['nombre', 'asociar_triaje_admision', 'mismos_servicios'])
                    ->all(),
            );
            $campania->fecha_inicio = $inicio;
            $campania->fecha_fin = $fin;
            $campania->created_by ??= $actorId;
            $campania->updated_by = $actorId;
            $campania->save();
            foreach ($anteriores as $anterior) {
                if (
                    ! isset($filas[$this->key($anterior->fecha_servicio->toDateString(), $anterior->ups_id)])
                ) {
                    $anterior->deleted_by = $actorId;
                    $anterior->delete();
                }
            }
            foreach ($filas as $key => $fila) {
                $registro =
                    $historicos->get($key) ??
                    new CampaniaServicio(['campania_id' => $campania->id, 'created_by' => $actorId]);
                $registro->fill($fila);
                $registro->updated_by = $actorId;
                $registro->save();
            }

            return $campania;
        });
    }

    /** @param array<string, mixed> $data
     * @param  Collection<int, CampaniaServicio>  $anteriores
     * @return array<string, array{
     *     ups_id: int,
     *     fecha_servicio: string,
     *     cupos: int,
     *     cupo_base: ?int,
     *     cupo_personalizado: bool
     * }>
     */
    private function prepareRows(
        array $data,
        CarbonImmutable $inicio,
        CarbonImmutable $fin,
        Collection $anteriores,
    ): array {
        $dias = [];
        foreach ($data['servicios_dia'] ?? [] as $fila) {
            $fecha = $fila['fecha'];
            $key = $this->key($fecha, (int) $fila['ups_id']);
            if ($fecha < $inicio->toDateString() || $fecha > $fin->toDateString() || isset($dias[$key])) {
                $this->fail('Hay servicios diarios duplicados o fechas fuera de la campaña.');
            }
            $dias[$key] = $fila;
        }
        $generales = [];
        foreach ($data['servicios_generales'] ?? [] as $fila) {
            $upsId = (int) $fila['ups_id'];
            if (isset($generales[$upsId])) {
                $this->fail('No se puede repetir un servicio en la lista general.');
            }
            $generales[$upsId] = (int) $fila['cupos'];
        }
        $previos = $anteriores->keyBy(
            fn (CampaniaServicio $fila): string => $this->key(
                $fila->fecha_servicio->toDateString(),
                $fila->ups_id,
            ),
        );
        $filas = [];
        for ($dia = $inicio; $dia->lte($fin); $dia = $dia->addDay()) {
            $fecha = $dia->toDateString();
            $servicios = (bool) $data['mismos_servicios']
                ? $generales
                : collect($dias)
                    ->where('fecha', $fecha)
                    ->pluck('cupos', 'ups_id')
                    ->all();
            if ($servicios === []) {
                $this->fail('Seleccione al menos un servicio con cupo por cada día de campaña.');
            }
            foreach ($servicios as $upsId => $cupo) {
                $key = $this->key($fecha, (int) $upsId);
                $anterior = $previos->get($key);
                $personalizado = false;
                $cupoEfectivo = (int) $cupo;
                if ((bool) $data['mismos_servicios']) {
                    $diario = $dias[$key]['cupos'] ?? null;
                    $cambioDiario =
                        $diario !== null &&
                        (int) $diario !== ($anterior?->cupos ?? (int) $cupo) &&
                        (int) $diario !== (int) $cupo;
                    if (
                        $cambioDiario ||
                        ($anterior?->cupo_personalizado && ! ($data['sobrescribir_personalizados'] ?? false))
                    ) {
                        $cupoEfectivo = (int) ($diario ?? $anterior->cupos);
                        $personalizado = true;
                    }
                }
                if ($cupoEfectivo < 1) {
                    $this->fail('Todos los cupos deben ser mayores que cero.');
                }
                $filas[$key] = [
                    'ups_id' => (int) $upsId,
                    'fecha_servicio' => $fecha.' 00:00:00',
                    'cupos' => $cupoEfectivo,
                    'cupo_base' => (bool) $data['mismos_servicios'] ? (int) $cupo : null,
                    'cupo_personalizado' => $personalizado,
                ];
            }
        }
        if ((bool) $data['mismos_servicios'] && array_diff_key($dias, $filas) !== []) {
            $this->fail('Los servicios diarios deben pertenecer a la lista general.');
        }

        return $filas;
    }

    /** @param list<int> $servicioIds
     * @return array<int, int>
     */
    public function usage(array $servicioIds): array
    {
        return DB::table('TB_HOJA_RUTA_DETALLE as d')
            ->join('TB_HOJAS_RUTA as h', 'h.id', '=', 'd.hoja_ruta_id')
            ->whereIn('d.campania_servicio_id', $servicioIds)
            ->whereNull('d.deleted_at')
            ->whereNull('h.deleted_at')
            ->groupBy('d.campania_servicio_id')
            ->selectRaw('d.campania_servicio_id, COUNT(*) as usados')
            ->pluck('usados', 'campania_servicio_id')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    public function close(Campania $campania, bool $cerrar, int $actorId): void
    {
        DB::transaction(function () use ($campania, $cerrar, $actorId): void {
            $campania = Campania::query()
                ->lockForUpdate()
                ->findOrFail($campania->id);
            if ($campania->cerrada === $cerrar) {
                return;
            }
            $campania->update([
                'cerrada' => $cerrar,
                'cerrada_at' => $cerrar ? now() : null,
                'cerrada_by' => $cerrar ? $actorId : null,
                'updated_by' => $actorId,
            ]);
        });
    }

    public function delete(Campania $campania, int $actorId): void
    {
        DB::transaction(function () use ($campania, $actorId): void {
            $campania = Campania::query()
                ->lockForUpdate()
                ->findOrFail($campania->id);
            $campania->assertEditable();
            if (
                $campania
                    ->hojas()
                    ->withTrashed()
                    ->exists()
            ) {
                $this->fail('No se puede eliminar una campaña con hojas de ruta registradas.');
            }
            foreach (['servicios', 'enlaces', 'asignaciones'] as $relacion) {
                foreach ($campania->$relacion()->get() as $registro) {
                    $registro->deleted_by = $actorId;
                    $registro->delete();
                }
            }
            $campania->deleted_by = $actorId;
            $campania->delete();
        });
    }

    private function key(string $fecha, int $upsId): string
    {
        return $fecha.':'.$upsId;
    }

    private function fail(string $mensaje): never
    {
        throw ValidationException::withMessages(['servicios' => $mensaje]);
    }
}
