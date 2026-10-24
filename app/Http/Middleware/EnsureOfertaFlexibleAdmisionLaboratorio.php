<?php

namespace App\Http\Middleware;

use App\Services\LaboratorioAdmisionService;
use App\Services\OfertaFlexibleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfertaFlexibleAdmisionLaboratorio
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            LaboratorioAdmisionService::puedeAcceder($request->user()),
            403,
            'Solo Admisión Laboratorio o el Administrador pueden acceder a este módulo.',
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
