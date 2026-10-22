<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoCampaniaRequest;
use App\Http\Requests\StoreCampaniaRequest;
use App\Http\Requests\UpdateCampaniaRequest;
use App\Models\Campania;
use App\Models\ServicioUps;
use App\Services\CampaniaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaniaController extends Controller
{
    public function __construct(protected CampaniaService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'estado' => 'nullable|in:Activa,Finalizada,Cerrada',
        ]);
        $query = Campania::query()->when(
            $filters['q'] ?? null,
            fn ($query, $q) => $query->where('nombre', 'like', '%'.$q.'%'),
        );
        $hoy = now('America/Lima')->startOfDay();
        $estado = $filters['estado'] ?? null;
        if ($estado === 'Cerrada') {
            $query->where('cerrada', 1);
        } elseif ($estado !== null) {
            $query->where('cerrada', 0);
            match ($estado) {
                'Finalizada' => $query->where('fecha_fin', '<', $hoy),
                'Activa' => $query->activas(),
                default => 'Finalizada',
            };
        }
        $campanias = $query
            ->addSelect([
                'servicios_count' => DB::table('TB_CAMPANIA_SERVICIOS')
                    ->selectRaw('COUNT(DISTINCT [ups_id])')
                    ->whereColumn('campania_id', 'TB_CAMPANIAS.id')
                    ->whereNull('deleted_at'),
            ])
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('oferta_flexible.campanias.index', compact('campanias'));
    }

    public function create(): View
    {
        return $this->form(new Campania);
    }

    public function store(StoreCampaniaRequest $request): RedirectResponse
    {
        $campania = $this->service->save(null, $request->validated(), $request->user()->id);

        return redirect()
            ->route('oferta-flexible.campanias.show', $campania)
            ->with('success', 'Campaña creada correctamente.');
    }

    public function show(Campania $campania): View
    {
        $campania->load([
            'servicios' => fn ($query) => $query->orderBy('fecha_servicio')->orderBy('id'),
            'servicios.servicioUps',
        ]);
        $usados = $this->service->usage($campania->servicios->pluck('id')->all());

        return view('oferta_flexible.campanias.show', compact('campania', 'usados'));
    }

    public function edit(Campania $campania): View
    {
        $campania->assertEditable();

        return $this->form($campania);
    }

    public function update(UpdateCampaniaRequest $request, Campania $campania): RedirectResponse
    {
        $this->service->save($campania, $request->validated(), $request->user()->id);

        return redirect()
            ->route('oferta-flexible.campanias.show', $campania)
            ->with('success', 'Campaña actualizada correctamente.');
    }

    public function destroy(Request $request, Campania $campania): JsonResponse
    {
        $this->service->delete($campania, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Campaña eliminada correctamente.']);
    }

    public function cerrar(CambiarEstadoCampaniaRequest $request, Campania $campania): RedirectResponse
    {
        $this->service->close($campania, true, $request->user()->id);

        return back()->with('success', 'Campaña cerrada. Solo se permite su consulta.');
    }

    public function reabrir(CambiarEstadoCampaniaRequest $request, Campania $campania): RedirectResponse
    {
        $this->service->close($campania, false, $request->user()->id);

        return back()->with('success', 'Campaña reabierta correctamente.');
    }

    private function form(Campania $campania): View
    {
        $campania->load('servicios');
        $servicios = ServicioUps::query()
            ->where(function ($query) use ($campania): void {
                $query->where('ACTIVO', 1)->orWhereIn('id', $campania->servicios->pluck('ups_id'));
            })
            ->orderBy('DESCRIPCION_UPS')
            ->get();
        $diarios = $campania->servicios
            ->map(
                fn ($fila): array => [
                    'fecha' => $fila->fecha_servicio->toDateString(),
                    'ups_id' => $fila->ups_id,
                    'cupos' => $fila->cupos,
                ],
            )
            ->all();
        $generales = $campania->servicios
            ->unique('ups_id')
            ->map(
                fn ($fila): array => ['ups_id' => $fila->ups_id, 'cupos' => $fila->cupo_base ?? $fila->cupos],
            )
            ->values()
            ->all();
        $personalizados = $campania->servicios
            ->where('cupo_personalizado', true)
            ->map(fn ($fila): string => $fila->fecha_servicio->toDateString().':'.$fila->ups_id)
            ->values()
            ->all();

        return view(
            'oferta_flexible.campanias.'.($campania->exists ? 'edit' : 'create'),
            compact('campania', 'servicios', 'diarios', 'generales', 'personalizados'),
        );
    }
}
