<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaboratorioTomaMuestraContextRequest;
use App\Http\Requests\LaboratorioTomaMuestraRequest;
use App\Services\LaboratorioAdmisionService;
use App\Services\LaboratorioStockService;
use App\Services\LaboratorioTomaMuestraService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class LaboratorioTomaMuestraController extends Controller
{
    public function index(
        LaboratorioTomaMuestraContextRequest $request,
        LaboratorioTomaMuestraService $service,
        LaboratorioStockService $stock,
    ): View|JsonResponse {
        $campanias = $service->campanias($request->user())
            ->orderBy('nombre')
            ->get();
        $id = $request->integer('campania_id');
        $seleccionada = $id ? $service->campania($request->user(), $id) : $campanias->first();
        if ($request->boolean('stock')) {
            abort_unless($seleccionada, 404);

            return response()->json(['stock' => $stock->delDia($seleccionada)])
                ->header('Cache-Control', 'no-store');
        }

        return view('oferta_flexible.laboratorio.toma_muestra', [
            'campanias' => $campanias,
            'seleccionada' => $seleccionada,
            'contexto' => $seleccionada ? $service->contexto($seleccionada) : null,
            'documentos' => LaboratorioAdmisionService::DOCUMENTOS,
            'asignacionUrl' => $seleccionada && Route::has('oferta-flexible.laboratorio.asignar.index')
                ? route('oferta-flexible.laboratorio.asignar.index', ['campania_id' => $seleccionada->id])
                : null,
        ]);
    }

    public function store(LaboratorioTomaMuestraRequest $request, LaboratorioTomaMuestraService $service): JsonResponse
    {
        return response()->json($service->operar($request->user(), $request->validated()))
            ->header('Cache-Control', 'no-store');
    }
}
