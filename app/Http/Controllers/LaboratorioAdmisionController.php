<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaboratorioAdmisionContextRequest;
use App\Http\Requests\LaboratorioAdmisionRequest;
use App\Http\Requests\LaboratorioEliminarRegistroRequest;
use App\Services\LaboratorioAdmisionService;
use App\Services\LaboratorioStockService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class LaboratorioAdmisionController extends Controller
{
    public function index(
        LaboratorioAdmisionContextRequest $request,
        LaboratorioAdmisionService $service,
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

        return view('oferta_flexible.laboratorio.admision', [
            'campanias' => $campanias,
            'seleccionada' => $seleccionada,
            'contexto' => $seleccionada ? $service->contexto($seleccionada) : null,
            'documentos' => LaboratorioAdmisionService::DOCUMENTOS,
        ]);
    }

    public function destroy(
        LaboratorioEliminarRegistroRequest $request,
        LaboratorioAdmisionService $service,
    ): JsonResponse {
        return response()->json($service->eliminar($request->user(), $request->validated()))
            ->header('Cache-Control', 'no-store');
    }

    public function store(LaboratorioAdmisionRequest $request, LaboratorioAdmisionService $service): JsonResponse
    {
        return response()->json($service->operar($request->user(), $request->validated()))
            ->header('Cache-Control', 'no-store');
    }
}
