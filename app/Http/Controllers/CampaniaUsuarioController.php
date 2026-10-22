<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaniaUsuarioRequest;
use App\Models\Campania;
use App\Models\CampaniaUsuario;
use App\Services\CampaniaUsuarioService;
use App\Services\OfertaFlexibleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaniaUsuarioController extends Controller
{
    public function __construct(protected CampaniaUsuarioService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'campania_id' => 'nullable|integer|exists:TB_CAMPANIAS,id',
            'solo_activas' => 'nullable|boolean',
        ]);
        $campanias = Campania::query()
            ->when($request->boolean('solo_activas'), fn ($query) => $query->activas())
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();
        $campania = isset($filters['campania_id'])
            ? Campania::query()
                ->with(['asignaciones.usuario', 'enlaces.medico', 'enlaces.admision'])
                ->findOrFail($filters['campania_id'])
            : null;
        $medicos = OfertaFlexibleAccess::usuariosConRol('Médico Triaje')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'user']);
        $admisiones = OfertaFlexibleAccess::usuariosConRol('Admisión')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'user']);

        return view(
            'oferta_flexible.usuarios.index',
            compact('campanias', 'campania', 'medicos', 'admisiones'),
        );
    }

    public function store(StoreCampaniaUsuarioRequest $request, Campania $campania): RedirectResponse
    {
        $this->service->assign($campania, $request->validated(), $request->user()->id);

        return back()->with('success', 'Usuarios asignados correctamente.');
    }

    public function destroy(Request $request, Campania $campania, CampaniaUsuario $asignacion): JsonResponse
    {
        $this->service->unassign($campania, $asignacion, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Asignación retirada correctamente.']);
    }
}
