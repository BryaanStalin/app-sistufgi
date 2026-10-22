<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = auth()->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if ($usuario->isAdministrador()) {
            return $next($request);
        }

        $rutaActual = $request->routeIs('oferta-flexible.admision.pdf')
            ? '/oferta-flexible/admision'
            : '/'.trim($request->path(), '/');
        $rutasPermitidas = $usuario->getAllowedMenuPaths();

        if (in_array($rutaActual, $rutasPermitidas, true)) {
            return $next($request);
        }

        return redirect()->route('dashboard')->with(
            'error',
            'Según su rol, no tiene acceso a esta visualización'
        );
    }
}
