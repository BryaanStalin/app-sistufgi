<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaImpresion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdmisionService
{
    public function __construct(protected HojaRutaPdfService $pdf) {}

    /** @return Collection<int, Campania> */
    public function campanias(User $usuario): Collection
    {
        return $this->campaniasAsignadas($usuario)
            ->where(fn (Builder $query) => $query->where('fecha_inicio', '<=', today('America/Lima'))->orWhere('cerrada', true))
            ->orderBy('cerrada')->orderByDesc('fecha_fin')->orderBy('id')->get();
    }

    /** @return Collection<int, User> */
    public function medicos(Campania $campania, User $usuario): Collection
    {
        $this->assertAdmision($usuario);
        $ids = $campania->asociar_triaje_admision
            ? $this->enlaces($campania, $usuario)->select('medico_usuario_id')
            : CampaniaUsuario::query()->where('campania_id', $campania->id)->where('tipo', 'M')->select('usuario_id');

        return User::query()->whereIn('id', $ids)->orderBy('nombre')->orderBy('id')->get(['id', 'nombre']);
    }

    /** @param array<string, mixed> $filters */
    public function listado(Campania $campania, User $usuario, array $filters): LengthAwarePaginator
    {
        $fecha = CarbonImmutable::parse($filters['fecha'], 'America/Lima')->startOfDay();
        $impresiones = DB::table('TB_HOJA_RUTA_IMPRESIONES')->select('hoja_ruta_id')
            ->selectRaw('COUNT(*) AS cantidad_impresiones, MAX(impreso_at) AS ultima_impresion')->groupBy('hoja_ruta_id');
        $query = $this->hojas($campania, $usuario)->toBase()
            ->join('TB_PACIENTES_MOCK as paciente', 'paciente.id', '=', 'TB_HOJAS_RUTA.paciente_id')
            ->join('TB_ACCESO_USUARIO as medico', 'medico.id', '=', 'TB_HOJAS_RUTA.medico_usuario_id')
            ->leftJoinSub($impresiones, 'impresiones', 'impresiones.hoja_ruta_id', '=', 'TB_HOJAS_RUTA.id')
            ->select(['TB_HOJAS_RUTA.id', 'TB_HOJAS_RUTA.created_at', 'TB_HOJAS_RUTA.updated_at',
                'paciente.tipo_doc', 'paciente.dni', 'paciente.nombres_apellidos', 'medico.nombre as medico',
                'impresiones.cantidad_impresiones', 'impresiones.ultima_impresion'])
            ->where('TB_HOJAS_RUTA.fecha_atencion', '>=', $fecha)
            ->where('TB_HOJAS_RUTA.fecha_atencion', '<', $fecha->addDay())
            ->when($filters['medico_id'] ?? null, fn ($query, $id) => $query->where('TB_HOJAS_RUTA.medico_usuario_id', $id))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($search) => $search
                ->where('paciente.dni', 'like', '%'.$term.'%')->orWhere('paciente.nombres_apellidos', 'like', '%'.$term.'%')))
            ->orderByDesc('TB_HOJAS_RUTA.created_at')->orderByDesc('TB_HOJAS_RUTA.id');
        $hojas = $query->paginate(15);
        if ($hojas->currentPage() > $hojas->lastPage()) {
            $hojas = $query->paginate(15, ['*'], 'page', $hojas->lastPage());
        }
        $hojas->appends($filters);
        $servicios = DB::table('TB_HOJA_RUTA_DETALLE as detalle')
            ->join('TB_CAMPANIA_SERVICIOS as servicio', 'servicio.id', '=', 'detalle.campania_servicio_id')
            ->join('TB_UPSS_CAMPAÑAS as ups', 'ups.id', '=', 'servicio.ups_id')
            ->whereIn('detalle.hoja_ruta_id', $hojas->getCollection()->pluck('id'))->whereNull('detalle.deleted_at')
            ->orderBy('detalle.id')->get(['detalle.hoja_ruta_id', 'ups.DESCRIPCION_UPS'])->groupBy('hoja_ruta_id');

        return $hojas->through(function (object $hoja) use ($servicios): object {
            $hoja->servicios = $servicios->get($hoja->id, collect())->pluck('DESCRIPCION_UPS')->all();
            $hoja->cantidad_impresiones = (int) $hoja->cantidad_impresiones;
            $hoja->modificada = $hoja->ultima_impresion !== null && CarbonImmutable::parse($hoja->updated_at)
                ->gt(CarbonImmutable::parse($hoja->ultima_impresion));
            $hoja->hora_registro = CarbonImmutable::parse($hoja->created_at)->format('H:i');
            $hoja->ultima_impresion_texto = $hoja->ultima_impresion ? CarbonImmutable::parse($hoja->ultima_impresion)->format('d/m/Y H:i:s') : 'Sin impresiones';

            return $hoja;
        });
    }

    public function imprimir(User $usuario, int $campaniaId, int $hojaId): string
    {
        return DB::transaction(function () use ($usuario, $campaniaId, $hojaId): string {
            $campania = $this->campaniasAsignadas($usuario)->lockForUpdate()->findOrFail($campaniaId);
            $hoja = $this->hojas($campania, $usuario)->lockForUpdate()->findOrFail($hojaId);
            $hoja->setRelation('campania', $campania)->load(['paciente', 'medico', 'detalles.campaniaServicio.servicioUps']);
            $emitidoAt = now('America/Lima');
            $contenido = $this->pdf->generar($hoja, $emitidoAt);
            HojaRutaImpresion::create(['hoja_ruta_id' => $hoja->id, 'impreso_por' => $usuario->id,
                'impreso_at' => $emitidoAt, 'created_at' => $emitidoAt, 'created_by' => $usuario->id]);

            return $contenido;
        }, attempts: 3);
    }

    public function eliminar(User $usuario, int $campaniaId, int $hojaId): void
    {
        DB::transaction(function () use ($usuario, $campaniaId, $hojaId): void {
            $campania = $this->campaniasAsignadas($usuario)->lockForUpdate()->findOrFail($campaniaId);
            $hoja = $this->hojas($campania, $usuario)->lockForUpdate()->findOrFail($hojaId);
            $campania->assertEditable();
            $detalles = $hoja->detalles()->orderBy('id')->lockForUpdate()->get();
            CampaniaServicio::query()->whereIn('id', $detalles->pluck('campania_servicio_id'))->orderBy('id')->lockForUpdate()->get();
            foreach ($detalles as $detalle) {
                $detalle->deleted_by = $usuario->id;
                $detalle->delete();
            }
            $hoja->deleted_by = $usuario->id;
            $hoja->delete();
        }, attempts: 3);
    }

    private function campaniasAsignadas(User $usuario): Builder
    {
        $this->assertAdmision($usuario);

        return Campania::query()->whereHas('asignaciones', fn (Builder $query) => $query->where('usuario_id', $usuario->id)->where('tipo', 'A'));
    }

    private function hojas(Campania $campania, User $usuario): Builder
    {
        $this->assertAdmision($usuario);

        return HojaRuta::query()->where('campania_id', $campania->id)
            ->whereHas('campania.asignaciones', fn (Builder $query) => $query->where('usuario_id', $usuario->id)->where('tipo', 'A'))
            ->when($campania->asociar_triaje_admision, fn (Builder $query) => $query
                ->whereIn('medico_usuario_id', $this->enlaces($campania, $usuario)->select('medico_usuario_id')));
    }

    private function enlaces(Campania $campania, User $usuario): Builder
    {
        return CampaniaEnlace::query()->where('campania_id', $campania->id)->where('admision_usuario_id', $usuario->id);
    }

    private function assertAdmision(User $usuario): void
    {
        abort_unless(
            OfertaFlexibleAccess::hasRole($usuario, 'Admisión') || OfertaFlexibleAccess::hasRole($usuario, 'Administrador'), 
            403, 
            'Solo Admisión puede realizar esta operación.');
    }
}
