<?php

namespace App\Http\Controllers;

use App\Http\Requests\TriajeRequest;
use App\Services\PacienteService;
use App\Services\TriajeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriajeController extends Controller
{
    public function __construct(protected TriajeService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate(['campania_id' => 'nullable|integer|min:1']);
        $campanias = $this->service->campanias($request->user());
        $seleccionada = isset($filters['campania_id'])
            ? $campanias->firstWhere('id', (int) $filters['campania_id'])
            : $campanias->first(fn ($campania): bool => $campania->estaEnPeriodoAtencion()) ??
                $campanias->first();
        if (isset($filters['campania_id'])) {
            abort_unless($seleccionada, 404);
        }

        return view('oferta_flexible.triaje.index', [
            'campanias' => $campanias,
            'seleccionada' => $seleccionada,
            'tiposDocumento' => PacienteService::TIPOS_DOCUMENTO,
        ]);
    }

    public function store(TriajeRequest $request): JsonResponse
    {
        $resultado = $this->service->operar($request->user(), $request->validated());

        return response()->json(['success' => true, ...$resultado]);
    }
}
