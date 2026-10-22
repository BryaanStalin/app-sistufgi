<?php

namespace App\Http\Middleware;

use App\Services\OfertaFlexibleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfertaFlexibleAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            OfertaFlexibleAccess::hasRole($request->user(), 'Administrador'),
            403,
            'Solo el Administrador puede acceder a este módulo.',
        );

        return $next($request);
    }
}
