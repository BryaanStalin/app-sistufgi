<?php

namespace App\Http\Middleware;

use App\Services\OfertaFlexibleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfertaFlexibleTriaje
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            OfertaFlexibleAccess::hasRole($request->user(), 'Médico Triaje') || OfertaFlexibleAccess::hasRole($request->user(), 'Administrador'),
            403,
            'Solo el Médico Triaje puede acceder a este módulo.'
        );

        return $next($request);
    }
}
