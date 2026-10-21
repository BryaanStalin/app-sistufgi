<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdmisionHojaRequest;
use App\Http\Requests\AdmisionListadoRequest;
use App\Services\AdmisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdmisionController extends Controller
{
    public function __construct(protected AdmisionService $service) {}

    public function index(AdmisionListadoRequest $request): View|JsonResponse
    {
        $filters = $request->validated();
        $filters['fecha'] = $filters['fecha'] ?? today('America/Lima')->toDateString();
        $campanias = $this->service->campanias($request->user());
        $campania = isset($filters['campania_id']) ? $campanias->firstWhere('id', (int) $filters['campania_id'])
            : ($campanias->first(fn ($opcion) => ! $opcion->cerrada && $opcion->fecha_fin->gte(today('America/Lima'))) ?? $campanias->first());
        abort_if(isset($filters['campania_id']) && ! $campania, 404);
        $medicos = $campania ? $this->service->medicos($campania, $request->user()) : collect();
        $hojas = $campania ? $this->service->listado($campania, $request->user(), $filters) : null;
        $data = compact('campanias', 'campania', 'medicos', 'hojas', 'filters');
        if ($request->expectsJson()) {
            return response()->json(['html' => view('oferta_flexible.admision._listado', $data)->render()])
                ->header('Cache-Control', 'private, no-store');
        }

        return view('oferta_flexible.admision.index', $data);
    }

    public function imprimir(AdmisionHojaRequest $request): Response
    {
        $data = $request->validated();
        $pdf = $this->service->imprimir($request->user(), (int) $data['campania_id'], (int) $data['hoja_id']);

        return response($pdf, 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="hoja-de-ruta.pdf"', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(AdmisionHojaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->eliminar($request->user(), (int) $data['campania_id'], (int) $data['hoja_id']);

        return response()->json(['success' => true, 'message' => 'Hoja de ruta eliminada. Los cupos están disponibles nuevamente.']);
    }
}
