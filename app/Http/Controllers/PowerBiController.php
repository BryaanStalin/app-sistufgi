<?php

namespace App\Http\Controllers;

use App\Services\PowerBiResumenService;
use Illuminate\Http\JsonResponse;

class PowerBiController extends Controller
{
    public function __construct(protected PowerBiResumenService $resumen) {}

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function resumen(string $campania): JsonResponse
    {
        return response()->json(['data' => $this->resumen->resumen($campania)]);
    }

    public function servicios(string $campania): JsonResponse
    {
        return response()->json(['data' => $this->resumen->servicios($campania)]);
    }
}
