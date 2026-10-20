<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Authenticate
{
    /**
     * Maneja la solicitud entrante
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        // Verificar que usuario esté activo
        if (!$user->status) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            Log::warning('Intento de acceso con usuario desactivado: ' . $user->user);
            
            return redirect()->route('login')
                ->with('error', 'Tu cuenta ha sido desactivada. Contacta al administrador.');
        }
        
        // Verificar que tenga al menos un rol activo
        $rolActivo = $user->roles()
            ->where('TB_ACCESO_USUARIO_X_ROL.status', 1)
            ->where('TB_ACCESO_ROL.status', 1)
            ->exists();
            
        if (!$rolActivo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            Log::warning('Intento de acceso con rol desactivado: ' . $user->user);
            
            return redirect()->route('login')
                ->with('error', 'Tu rol se encuentra desactivado. Contacta al administrador.');
        }

        return $next($request);
    }
}
