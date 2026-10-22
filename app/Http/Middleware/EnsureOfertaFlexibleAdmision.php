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
            OfertaFlexibleAccess::puedeConsultarPacientes($request->user()),
            403,
            'Solo Admisión o el Administrador pueden acceder a este módulo.',
        );

        return $next($request);
    }
}
