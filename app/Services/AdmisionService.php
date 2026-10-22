<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaImpresion;
use App\Models\PacienteMock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AdmisionService
{
    public function __construct(protected HojaRutaPdfService $pdf) {}

    /** @return Collection<int, Campania> */
    public function campanias(User $usuario): Collection
    {
        return $this
            ->campaniasAccesibles($usuario)
            ->when(
                OfertaFlexibleAccess::hasRole($usuario, 'Administrador'),
                fn (Builder $query) => $query->activas(),
            )
            ->orderBy('cerrada')
            ->orderByDesc('fecha_fin')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, User> */
    public function medicos(Campania $campania, User $usuario): Collection
    {
        $this->assertPuedeConsultarPacientes($usuario);
        if (OfertaFlexibleAccess::hasRole($usuario, 'Administrador')) {
            $ids = $this->hojas($campania, $usuario)->select('medico_usuario_id');
        } else {
            $ids = $campania->asociar_triaje_admision
                ? $this->enlaces($campania, $usuario)->select('medico_usuario_id')
                : CampaniaUsuario::query()
                    ->where('campania_id', $campania->id)
                    ->where('tipo', 'M')
                    ->select('usuario_id');
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre']);
    }

    /** @param array<string, mixed> $filters */
    public function listado(Campania $campania, User $usuario, array $filters): LengthAwarePaginator
    {
        $query = $this->consultaListado($campania, $usuario, $filters);
        $hojas = $query->paginate(15);
        if ($hojas->currentPage() > $hojas->lastPage()) {
            $hojas = $query->paginate(15, ['*'], 'page', $hojas->lastPage());
        }
        $hojas->appends($filters);
        $servicios = DB::table('TB_HOJA_RUTA_DETALLE as detalle')
            ->join('TB_CAMPANIA_SERVICIOS as servicio', 'servicio.id', '=', 'detalle.campania_servicio_id')
            ->join('TB_UPSS_CAMPAÑAS as ups', 'ups.id', '=', 'servicio.ups_id')
            ->whereIn('detalle.hoja_ruta_id', $hojas->getCollection()->pluck('id'))
            ->whereNull('detalle.deleted_at')
            ->orderBy('detalle.id')
            ->get(['detalle.hoja_ruta_id', 'ups.DESCRIPCION_UPS'])
            ->groupBy('hoja_ruta_id');

        return $hojas->through(function (object $hoja) use ($servicios): object {
            $hoja->nombre_completo = (new PacienteMock([
                'nombres' => $hoja->nombres,
                'apellidos' => $hoja->apellidos,
            ]))->nombre_completo;
            $hoja->servicios = $servicios
                ->get($hoja->id, collect())
                ->pluck('DESCRIPCION_UPS')
                ->all();
            $hoja->cantidad_impresiones = (int) $hoja->cantidad_impresiones;
            $hoja->modificada =
                $hoja->ultima_impresion !== null &&
                CarbonImmutable::parse($hoja->updated_at)->gt(
                    CarbonImmutable::parse($hoja->ultima_impresion),
                );
            $hoja->hora_registro = CarbonImmutable::parse($hoja->created_at)->format('H:i');
            $hoja->ultima_impresion_texto = $hoja->ultima_impresion
                ? CarbonImmutable::parse($hoja->ultima_impresion)->format('d/m/Y H:i:s')
                : 'Sin impresiones';

            return $hoja;
        });
    }

    /** @param array<string, mixed> $filters */
    private function consultaListado(Campania $campania, User $usuario, array $filters): QueryBuilder
    {
        $fecha = CarbonImmutable::parse($filters['fecha'], 'America/Lima')->startOfDay();
        $impresiones = DB::table('TB_HOJA_RUTA_IMPRESIONES')
            ->select('hoja_ruta_id')
            ->selectRaw('COUNT(*) AS cantidad_impresiones, MAX(impreso_at) AS ultima_impresion')
            ->groupBy('hoja_ruta_id');

        return $this
            ->hojas($campania, $usuario)
            ->toBase()
            ->join('TB_PACIENTES_MOCK as paciente', 'paciente.id', '=', 'TB_HOJAS_RUTA.paciente_id')
            ->join('TB_ACCESO_USUARIO as medico', 'medico.id', '=', 'TB_HOJAS_RUTA.medico_usuario_id')
            ->leftJoinSub($impresiones, 'impresiones', 'impresiones.hoja_ruta_id', '=', 'TB_HOJAS_RUTA.id')
            ->select([
                'TB_HOJAS_RUTA.id',
                'TB_HOJAS_RUTA.created_at',
                'TB_HOJAS_RUTA.updated_at',
                'paciente.tipo_doc',
                'paciente.dni',
                'paciente.nombres',
                'paciente.apellidos',
                'medico.nombre as medico',
                'impresiones.cantidad_impresiones',
                'impresiones.ultima_impresion',
            ])
            ->where('TB_HOJAS_RUTA.fecha_atencion', '>=', $fecha)
            ->where('TB_HOJAS_RUTA.fecha_atencion', '<', $fecha->addDay())
            ->when(
                $filters['medico_id'] ?? null,
                fn ($query, $id) => $query->where('TB_HOJAS_RUTA.medico_usuario_id', $id),
            )
            ->when(
                $filters['q'] ?? null,
                function ($query, string $term): void {
                    $palabras = preg_split('/[\s\p{Z}]+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY);
                    $query->where(function ($search) use ($term, $palabras): void {
                        $search->where('paciente.dni', 'like', '%'.$term.'%')
                            ->orWhere(function ($nombres) use ($palabras): void {
                                foreach ($palabras as $palabra) {
                                    $nombres->where(
                                        fn ($parte) => $parte
                                            ->where('paciente.nombres', 'like', '%'.$palabra.'%')
                                            ->orWhere('paciente.apellidos', 'like', '%'.$palabra.'%'),
                                    );
                                }
                            });
                    });
                },
            )
            ->orderByDesc('TB_HOJAS_RUTA.created_at')
            ->orderByDesc('TB_HOJAS_RUTA.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total: int, impresas: int, pendientes: int, modificadas: int}
     */
    public function resumen(Campania $campania, User $usuario, array $filters): array
    {
        $conteos = $this
            ->consultaListado($campania, $usuario, $filters)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS [total]')
            ->selectRaw(
                'COUNT(CASE WHEN [impresiones].[ultima_impresion] IS NOT NULL THEN 1 END) AS [impresas]',
            )
            ->selectRaw(
                'COUNT(CASE WHEN [impresiones].[ultima_impresion] IS NULL THEN 1 END) AS [pendientes]',
            )
            ->selectRaw(
                'COUNT(CASE WHEN [TB_HOJAS_RUTA].[updated_at] > [impresiones].[ultima_impresion] '.
                    'THEN 1 END) AS [modificadas]',
            )
            ->first();

        return [
            'total' => (int) $conteos->total,
            'impresas' => (int) $conteos->impresas,
            'pendientes' => (int) $conteos->pendientes,
            'modificadas' => (int) $conteos->modificadas,
        ];
    }

    public function imprimir(User $usuario, int $campaniaId, int $hojaId): string
    {
        $token = Str::random(64);

        try {
            return DB::transaction(function () use ($usuario, $campaniaId, $hojaId, $token): string {
                $campania = $this
                    ->campaniasAccesibles($usuario)
                    ->lockForUpdate()
                    ->findOrFail($campaniaId);
                $hoja = $this
                    ->hojas($campania, $usuario)
                    ->lockForUpdate()
                    ->findOrFail($hojaId);
                $hoja
                    ->setRelation('campania', $campania)
                    ->load(['paciente', 'medico', 'detalles.campaniaServicio.servicioUps']);
                $emitidoAt = now('America/Lima');
                $contenido = $this->pdf->generar($hoja, $emitidoAt);
                $this->pdf->guardarTemporal($token, $usuario, $hoja, $contenido);
                HojaRutaImpresion::create([
                    'hoja_ruta_id' => $hoja->id,
                    'impreso_por' => $usuario->id,
                    'impreso_at' => $emitidoAt,
                    'created_at' => $emitidoAt,
                    'created_by' => $usuario->id,
                ]);

                return $token;
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->pdf->olvidarTemporal($token);

            throw $exception;
        }
    }

    /** @return array{usuario_id: int, campania_id: int, hoja_id: int, nombre: string, contenido: string} */
    public function consultarPdf(User $usuario, string $token): array
    {
        $documento = $this->pdf->recuperarTemporal($usuario, $token);
        $campania = $this->campaniasAccesibles($usuario)->findOrFail($documento['campania_id']);
        $this->hojas($campania, $usuario)->findOrFail($documento['hoja_id']);

        return $documento;
    }

    public function eliminar(User $usuario, int $campaniaId, int $hojaId): void
    {
        DB::transaction(function () use ($usuario, $campaniaId, $hojaId): void {
            $campania = $this
                ->campaniasAccesibles($usuario)
                ->lockForUpdate()
                ->findOrFail($campaniaId);
            $hoja = $this
                ->hojas($campania, $usuario)
                ->lockForUpdate()
                ->findOrFail($hojaId);
            $campania->assertEditable();
            $detalles = $hoja
                ->detalles()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            CampaniaServicio::query()
                ->whereIn('id', $detalles->pluck('campania_servicio_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($detalles as $detalle) {
                $detalle->deleted_by = $usuario->id;
                $detalle->delete();
            }
            $hoja->deleted_by = $usuario->id;
            $hoja->delete();
        }, attempts: 3);
    }

    private function campaniasAccesibles(User $usuario): Builder
    {
        $this->assertPuedeConsultarPacientes($usuario);

        if (OfertaFlexibleAccess::hasRole($usuario, 'Administrador')) {
            return Campania::query()->where(
                fn (Builder $query) => $query->activas()->orWhere('cerrada', true),
            );
        }

        return Campania::query()->whereHas(
            'asignaciones',
            fn (Builder $query) => $query->where('usuario_id', $usuario->id)->where('tipo', 'A'),
        );
    }

    private function hojas(Campania $campania, User $usuario): Builder
    {
        $this->assertPuedeConsultarPacientes($usuario);

        if (OfertaFlexibleAccess::hasRole($usuario, 'Administrador')) {
            return HojaRuta::query()
                ->where('campania_id', $campania->id)
                ->whereIn('campania_id', $this->campaniasAccesibles($usuario)->select('id'));
        }

        return HojaRuta::query()
            ->where('campania_id', $campania->id)
            ->whereHas(
                'campania.asignaciones',
                fn (Builder $query) => $query->where('usuario_id', $usuario->id)->where('tipo', 'A'),
            )
            ->when(
                $campania->asociar_triaje_admision,
                fn (Builder $query) => $query->whereIn(
                    'medico_usuario_id',
                    $this->enlaces($campania, $usuario)->select('medico_usuario_id'),
                ),
            );
    }

    private function enlaces(Campania $campania, User $usuario): Builder
    {
        return CampaniaEnlace::query()
            ->where('campania_id', $campania->id)
            ->where('admision_usuario_id', $usuario->id);
    }

    private function assertPuedeConsultarPacientes(User $usuario): void
    {
        abort_unless(
            OfertaFlexibleAccess::puedeConsultarPacientes($usuario),
            403,
            'Solo Admisión o el Administrador pueden realizar esta operación.',
        );
    }
}
