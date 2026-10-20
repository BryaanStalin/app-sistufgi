<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfAuthenticated
{
    /**
     * Maneja la solicitud entrante
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
