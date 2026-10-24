<?php

namespace App\Services;

use App\Models\Campania;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorioStockService
{
    /** @return list<array<string, int|string|bool|null>> */
    public function tabla(Campania $campania, ?string $fecha = null): array
    {
        if (! $campania->incluye_laboratorio) {
            return [];
        }
        $conteos = DB::table('TB_LAB_ATENCION_EXAMENES as examen')
            ->join('TB_LAB_ATENCIONES as atencion', 'atencion.id', '=', 'examen.atencion_id')
            ->join('TB_LAB_PACIENTES as paciente', 'paciente.id', '=', 'examen.lab_paciente_id')
            ->join('TB_CAMPANIAS as campania', 'campania.id', '=', 'atencion.campania_id')
            ->where('atencion.campania_id', $campania->id)
            ->whereColumn('paciente.campania_id', 'atencion.campania_id')
            ->whereColumn('paciente.id', 'atencion.lab_paciente_id')
            ->whereNull('campania.deleted_at')
            ->whereNull('examen.deleted_at')
            ->whereNull('atencion.deleted_at')
            ->whereNull('paciente.deleted_at')
            ->groupBy('examen.campania_servicio_id', 'atencion.fecha_atencion')
            ->select('examen.campania_servicio_id', 'atencion.fecha_atencion')
            ->selectRaw("SUM(CASE WHEN examen.estado IN ('P', 'T') THEN 1 ELSE 0 END) AS registrados")
            ->selectRaw("SUM(CASE WHEN examen.estado = 'T' THEN 1 ELSE 0 END) AS tomadas")
            ->selectRaw("SUM(CASE WHEN examen.estado = 'N' THEN 1 ELSE 0 END) AS no_realizadas");

        return DB::table('TB_CAMPANIA_SERVICIOS as servicio')
            ->join('TB_UPSS_CAMPAÑAS as ups', 'ups.id', '=', 'servicio.ups_id')
            ->join('TB_CAMPANIAS as campania', 'campania.id', '=', 'servicio.campania_id')
            ->leftJoinSub($conteos, 'conteos', function ($join): void {
                $join->on('conteos.campania_servicio_id', '=', 'servicio.id')
                    ->on('conteos.fecha_atencion', '=', 'servicio.fecha_servicio');
            })
            ->where('servicio.campania_id', $campania->id)
            ->where('ups.TIPO_UPS', 'L')
            ->whereNull('campania.deleted_at')
            ->whereNull('servicio.deleted_at')
            ->when($fecha !== null, fn ($query) => $query->where(
                'servicio.fecha_servicio', CarbonImmutable::parse($fecha, 'America/Lima')->startOfDay(),
            ))
            ->orderBy('servicio.fecha_servicio')
            ->orderBy('ups.ID_UPS')
            ->select([
                'servicio.id', 'servicio.ups_id', 'servicio.fecha_servicio',
                'servicio.cupos', 'servicio.cupo_personalizado',
                'ups.DESCRIPCION_UPS', 'ups.ACTIVO',
            ])
            ->selectRaw('COALESCE(conteos.registrados, 0) AS registrados')
            ->selectRaw('COALESCE(conteos.tomadas, 0) AS tomadas')
            ->selectRaw('COALESCE(conteos.no_realizadas, 0) AS no_realizadas')
            ->get()
            ->map(function (object $fila): array {
                $estado = $this->calcular(
                    (int) $fila->cupos, (int) $fila->registrados,
                    (int) $fila->tomadas, (int) $fila->no_realizadas,
                    $fila->DESCRIPCION_UPS,
                );

                return [
                    'campania_servicio_id' => (int) $fila->id,
                    'ups_id' => (int) $fila->ups_id,
                    'examen' => $fila->DESCRIPCION_UPS,
                    'fecha' => CarbonImmutable::parse($fila->fecha_servicio)->toDateString(),
                    'personalizado' => (bool) $fila->cupo_personalizado,
                    'disponible' => (bool) $fila->ACTIVO && $estado['quedan'] > 0,
                    ...$estado,
                ];
            })
            ->all();
    }

    /** @return list<array<string, int|string|bool|null>> */
    public function delDia(Campania $campania): array
    {
        return $this->tabla($campania, today('America/Lima')->toDateString());
    }

    /** @return array<string, int|string|bool|null>|null */
    public function examen(Campania $campania, int $upsId, string $fecha): ?array
    {
        foreach ($this->tabla($campania, $fecha) as $fila) {
            if ($fila['ups_id'] === $upsId) {
                return $fila;
            }
        }

        return null;
    }

    /** @return array{insumos: int, registrados: int, tomadas: int, no_realizadas: int,
     *     quedan: int, rojo: bool, alerta: bool, mensaje_alerta: ?string}
     */
    public function calcular(int $insumos, int $registrados, int $tomadas, int $noRealizadas, string $nombre): array
    {
        $quedan = max(0, $insumos - $registrados);
        $alerta = $quedan <= 10;

        return [
            'insumos' => $insumos,
            'registrados' => $registrados,
            'tomadas' => $tomadas,
            'no_realizadas' => $noRealizadas,
            'quedan' => $quedan,
            'rojo' => $quedan <= (int) ceil($insumos * 0.15),
            'alerta' => $alerta,
            'mensaje_alerta' => $alerta
                ? 'NOS ESTAMOS QUEDANDO SIN INSUMOS DE “'.$nombre.'” ABASTEZCA A TRAVÉS DEL ADMINISTRADOR '.
                    'O INDIQUE A LOS PACIENTES QUE ESTA PRUEBA ESTARÁ RESTRINGIDA DENTRO DE POCO'
                : null,
        ];
    }

    public function assertDisponible(Campania $campania, int $upsId, string $fecha): void
    {
        $stock = $this->examen($campania, $upsId, $fecha);
        if ($stock === null || ! $stock['disponible']) {
            throw ValidationException::withMessages([
                'examenes' => $stock ? 'No hay insumos de '.$stock['examen'] : 'El examen no tiene insumos para hoy.',
            ]);
        }
    }
}
