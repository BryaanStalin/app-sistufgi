<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdmisionHojaRequest;
use App\Http\Requests\AdmisionListadoRequest;
use App\Services\AdmisionService;
use App\Services\OfertaFlexibleAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmisionController extends Controller
{
    public function __construct(protected AdmisionService $service) {}

    public function index(AdmisionListadoRequest $request): View|JsonResponse
    {
        $filters = $request->validated();
        $filters['fecha'] = $filters['fecha'] ?? today('America/Lima')->toDateString();
        $esAdministrador = OfertaFlexibleAccess::hasRole($request->user(), 'Administrador');
        $campanias = $this->service->campanias($request->user());
        $campania = isset($filters['campania_id'])
            ? $campanias->firstWhere('id', (int) $filters['campania_id'])
            : $campanias->first(fn ($opcion): bool => $opcion->estaEnPeriodoAtencion()) ?? $campanias->first();
        abort_if(isset($filters['campania_id']) && ! $campania, 404);
        $medicos = $campania ? $this->service->medicos($campania, $request->user()) : collect();
        $hojas = $campania ? $this->service->listado($campania, $request->user(), $filters) : null;
        $resumen = $campania ? $this->service->resumen($campania, $request->user(), $filters) : null;
        $data = compact('campanias', 'campania', 'medicos', 'hojas', 'filters', 'esAdministrador', 'resumen');
        if ($request->expectsJson()) {
            return response()
                ->json(['html' => view('oferta_flexible.admision._listado', $data)->render()])
                ->header('Cache-Control', 'private, no-store');
        }

        return view('oferta_flexible.admision.index', $data);
    }

    public function imprimir(AdmisionHojaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $token = $this->service->imprimir($request->user(), (int) $data['campania_id'], (int) $data['hoja_id']);

        return to_route('oferta-flexible.admision.pdf', ['token' => $token], 303, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pdf(Request $request, string $token): Response
    {
        $documento = $this->service->consultarPdf($request->user(), $token);

        return response($documento['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($documento['contenido']),
            'Content-Disposition' => 'inline; filename="'.$documento['nombre'].'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function destroy(AdmisionHojaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->service->eliminar($request->user(), (int) $data['campania_id'], (int) $data['hoja_id']);

        return response()->json([
            'success' => true,
            'message' => 'Hoja de ruta eliminada. Los cupos están disponibles nuevamente.',
        ]);
    }
}
