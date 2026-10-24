<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaboratorioStockRequest;
use App\Models\Campania;
use App\Services\LaboratorioStockService;
use Illuminate\Http\JsonResponse;

class LaboratorioStockController extends Controller
{
    public function __invoke(
        LaboratorioStockRequest $request,
        Campania $campania,
        LaboratorioStockService $stock,
    ): JsonResponse {
        $filas = $stock->tabla($campania, $request->validated('fecha'));

        return response()->json([
            'html' => view('oferta_flexible.partials.laboratorio_stock', ['stock' => $filas])->render(),
            'generado_en' => now('America/Lima')->format('H:i:s'),
        ])->header('Cache-Control', 'no-store');
    }
}
