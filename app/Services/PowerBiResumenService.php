<?php

namespace App\Services;

use App\Models\Campania;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PowerBiResumenService
{
    /**
     * @return array{
     *     campania_id: int, campania: string, fecha_inicio: string, fecha_fin: string,
     *     estado: string, atendidos: int, atenciones: int, generado_en: string
     * }
     */
    public function resumen(string $id): array
    {
        $campania = $this->campania($id);
        $atendidos = $this->hojasActivas($campania->id)
            ->distinct()
            ->count('hoja.paciente_id');
        $atenciones = $this->detallesActivos($campania->id)->count('detalle.id');

        return [
            'campania_id' => (int) $campania->id,
            'campania' => $campania->nombre,
            'fecha_inicio' => $campania->fecha_inicio->toDateString(),
            'fecha_fin' => $campania->fecha_fin->toDateString(),
            'estado' => $campania->estado,
            'atendidos' => (int) $atendidos,
            'atenciones' => (int) $atenciones,
            'generado_en' => now('America/Lima')->toIso8601String(),
        ];
    }

    /** @return list<array{ups_id: int, servicio: string, tipo_servicio: string, pacientes: int}> */
    public function servicios(string $id): array
    {
        $campania = $this->campania($id);
        $configurados = DB::table('TB_CAMPANIA_SERVICIOS')
            ->where('campania_id', $campania->id)
            ->whereNull('deleted_at')
            ->select('ups_id')
            ->distinct();
        $pacientes = $this->detallesActivos($campania->id)
            ->select('servicio_campania.ups_id')
            ->selectRaw('COUNT(DISTINCT hoja.paciente_id) AS pacientes')
            ->groupBy('servicio_campania.ups_id');

        return DB::query()
            ->fromSub($configurados, 'configurados')
            ->join('TB_UPSS_CAMPAÑAS as ups', 'ups.id', '=', 'configurados.ups_id')
            ->whereIn('ups.TIPO_UPS', ['P', 'E'])
            ->leftJoinSub($pacientes, 'conteos', 'conteos.ups_id', '=', 'ups.id')
            ->select(['ups.id as ups_id', 'ups.DESCRIPCION_UPS as servicio', 'ups.TIPO_UPS as tipo_servicio'])
            ->selectRaw('COALESCE(conteos.pacientes, 0) AS pacientes')
            ->orderByDesc('pacientes')
            ->orderBy('ups.DESCRIPCION_UPS')
            ->orderBy('ups.id')
            ->get()
            ->map(fn (object $fila): array => [
                'ups_id' => (int) $fila->ups_id,
                'servicio' => $fila->servicio,
                'tipo_servicio' => $fila->tipo_servicio,
                'pacientes' => (int) $fila->pacientes,
            ])
            ->all();
    }

    private function campania(string $id): Campania
    {
        $numero = filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);
        abort_if($numero === false, 404, 'Campaña no encontrada');

        return Campania::query()
            ->select(['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'cerrada'])
            ->findOrFail($numero);
    }

    private function hojasActivas(int $campania): Builder
    {
        return DB::table('TB_HOJAS_RUTA as hoja')
            ->where('hoja.campania_id', $campania)
            ->whereNull('hoja.deleted_at');
    }

    private function detallesActivos(int $campania): Builder
    {
        return $this->hojasActivas($campania)
            ->join('TB_HOJA_RUTA_DETALLE as detalle', 'detalle.hoja_ruta_id', '=', 'hoja.id')
            ->join(
                'TB_CAMPANIA_SERVICIOS as servicio_campania',
                'servicio_campania.id',
                '=',
                'detalle.campania_servicio_id',
            )
            ->join('TB_UPSS_CAMPAÑAS as ups', 'ups.id', '=', 'servicio_campania.ups_id')
            ->whereIn('ups.TIPO_UPS', ['P', 'E'])
            ->where('servicio_campania.campania_id', $campania)
            ->whereNull('detalle.deleted_at')
            ->whereNull('servicio_campania.deleted_at');
    }
}
