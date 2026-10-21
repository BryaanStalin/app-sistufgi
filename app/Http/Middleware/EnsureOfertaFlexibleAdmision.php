<?php

namespace App\Http\Middleware;

use App\Services\OfertaFlexibleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfertaFlexibleAdmision
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            OfertaFlexibleAccess::hasRole($request->user(), 'Admisión') || OfertaFlexibleAccess::hasRole($request->user(), 'Administrador'),
            403, 
            'Solo Admisión puede acceder a este módulo.');

        return $next($request);
    }
}
