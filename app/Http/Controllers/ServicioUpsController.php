<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicioUpsRequest;
use App\Http\Requests\UpdateServicioUpsRequest;
use App\Models\ServicioUps;
use App\Services\ServicioUpsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServicioUpsController extends Controller
{
    public function __construct(protected ServicioUpsService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:255', 'tipo' => 'nullable|in:P,E', 'activo' => 'nullable|in:0,1']);
        $servicios = ServicioUps::query()
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(function ($query) use ($q): void {
                $query->where('DESCRIPCION_UPS', 'like', '%'.$q.'%');
                if (ctype_digit($q)) {
                    $query->orWhere('ID_UPS', (int) $q);
                }
            }))
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('TIPO_UPS', $tipo))
            ->when(isset($filters['activo']) && $filters['activo'] !== null, fn ($query) => $query->where('ACTIVO', $filters['activo']))
            ->orderBy('DESCRIPCION_UPS')->orderBy('id')->paginate(20)->withQueryString();

        return view('oferta_flexible.servicios.index', compact('servicios'));
    }

    public function create(): View
    {
        return view('oferta_flexible.servicios.create');
    }

    public function store(StoreServicioUpsRequest $request): RedirectResponse
    {
        $this->service->save(null, $request->validated(), $request->user()->id);

        return redirect()->route('oferta-flexible.servicios.index')->with('success', 'Servicio UPSS creado correctamente.');
    }

    public function edit(ServicioUps $servicio): View
    {
        return view('oferta_flexible.servicios.edit', compact('servicio'));
    }

    public function update(UpdateServicioUpsRequest $request, ServicioUps $servicio): RedirectResponse
    {
        $this->service->save($servicio, $request->validated(), $request->user()->id);

        return redirect()->route('oferta-flexible.servicios.index')->with('success', 'Servicio UPSS actualizado correctamente.');
    }

    public function destroy(Request $request, ServicioUps $servicio): JsonResponse
    {
        $this->service->delete($servicio, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Servicio UPSS desactivado correctamente.']);
    }
}
