<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaboratorioAsignacionContextRequest;
use App\Http\Requests\LaboratorioAsignacionRequest;
use App\Services\LaboratorioAsignacionExcelService;
use App\Services\LaboratorioAsignacionService;
use App\Services\LaboratorioEstablecimientoService;
use App\Services\LaboratorioTmService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LaboratorioAsignacionController extends Controller
{
    public function index(
        LaboratorioAsignacionContextRequest $request,
        LaboratorioAsignacionService $service,
        LaboratorioEstablecimientoService $establecimientos,
        LaboratorioAsignacionExcelService $excel,
        LaboratorioTmService $tm,
    ): View|JsonResponse|BinaryFileResponse {
        $campanias = $service->campanias($request->user())
            ->orderByRaw(
                'CASE WHEN fecha_inicio <= ? AND fecha_fin >= ? THEN 0 ELSE 1 END',
                [today('America/Lima'), today('America/Lima')],
            )
            ->orderBy('nombre')
            ->get();
        $id = $request->integer('campania_id');
        $seleccionada = $id ? $service->campania($request->user(), $id) : $campanias->first();
        $filtros = [
            'q' => $request->validated('q') ?? '',
            'asignacion' => $request->validated('asignacion') ?? 'todos',
            'por_pagina' => $request->integer('por_pagina') ?: 25,
        ];
        $columnas = $seleccionada ? $service->columnas($seleccionada) : collect();
        $eess = $seleccionada ? $establecimientos->disponibles() : [];
        if ($request->boolean('exportar')) {
            abort_unless($seleccionada, 404);

            return $excel->exportar($request->user(), $seleccionada, $filtros, $columnas, $eess);
        }
        $pacientes = $seleccionada ? $service->consulta($seleccionada, $filtros, $columnas)
            ->paginate($filtros['por_pagina']) : null;
        if ($pacientes && $pacientes->currentPage() > $pacientes->lastPage()) {
            $pacientes = $service->consulta($seleccionada, $filtros, $columnas)
                ->paginate($filtros['por_pagina'], ['*'], 'page', $pacientes->lastPage());
        }
        $parametros = ['campania_id' => $seleccionada?->id, ...$filtros];
        $pacientes?->appends($parametros);
        $datos = compact('campanias', 'seleccionada', 'filtros', 'columnas', 'eess', 'pacientes', 'tm') + [
            'exportacionUrl' => route('oferta-flexible.laboratorio.asignar.index', $parametros + ['exportar' => 1]),
        ];
        if ($request->boolean('tabla')) {
            abort_unless($seleccionada, 404);

            return response()->json([
                'html' => view('oferta_flexible.laboratorio._asignacion_tabla', $datos)->render(),
                'pagina' => $pacientes->currentPage(),
            ])->header('Cache-Control', 'no-store');
        }

        return view('oferta_flexible.laboratorio.asignar', $datos);
    }

    public function store(LaboratorioAsignacionRequest $request, LaboratorioAsignacionService $service): JsonResponse
    {
        $data = $request->validated();
        $cantidad = $service->asignar(
            $request->user(), (int) $data['campania_id'], $data['pacientes'], $data['renipress'],
        );

        return response()->json([
            'message' => 'Laboratorio asignado correctamente.',
            'cantidad' => $cantidad,
        ])->header('Cache-Control', 'no-store');
    }
}
