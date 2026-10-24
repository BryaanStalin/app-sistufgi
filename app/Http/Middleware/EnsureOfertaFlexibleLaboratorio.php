<?php

namespace App\Http\Middleware;

use App\Services\LaboratorioTomaMuestraService;
use App\Services\OfertaFlexibleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfertaFlexibleLaboratorio
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            LaboratorioTomaMuestraService::puedeAcceder($request->user()),
            403,
            'Solo Laboratorio o el Administrador pueden acceder a este módulo.',
        );

        abort_unless(
            OfertaFlexibleAccess::hasRole($request->user(), 'Administrador') ||
                in_array('/'.trim($request->path(), '/'), $request->user()->getAllowedMenuPaths(), true),
            403,
            'No tiene permiso para acceder a esta pantalla de laboratorio.',
        );

        return $next($request);
    }
}
