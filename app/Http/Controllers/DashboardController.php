<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Mostrar dashboard principal
     */
    public function index()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
        
            // Verificación adicional de seguridad
            if (!$user->status) {
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', 'Tu cuenta ha sido desactivada');
            }
            
            $rolActivo = $user->roles()
                ->where('TB_ACCESO_USUARIO_X_ROL.status', 1)
                ->where('TB_ACCESO_ROL.status', 1)
                ->exists();
                
            if (!$rolActivo) {
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', 'Tu rol se encuentra desactivado');
            }

            $rolUsuario = Auth::user()->roles->first();

            return view('dashboard.index', [
                'user' => $user,
                'rol' => $rolUsuario
            ]);
        } catch (\Exception $e) {
            logger()->error('Error en dashboard: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Error al cargar el dashboard');
        }
    }

    public function welcome()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
        
            // Verificación adicional de seguridad
            if (!$user->status) {
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', 'Tu cuenta ha sido desactivada');
            }
            
            $rolActivo = $user->roles()
                ->where('TB_ACCESO_USUARIO_X_ROL.status', 1)
                ->where('TB_ACCESO_ROL.status', 1)
                ->exists();
                
            if (!$rolActivo) {
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', 'Tu rol se encuentra desactivado');
            }

            return view('welcome');
        } catch (\Exception $e) {
            logger()->error('Error en dashboard: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Error al cargar el dashboard');
        }
    }
}
