<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginFormRequest;
use App\Services\AuthService;
use App\Models\LogAcceso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Mostrar formulario de login
     */
    public function create()
    {
        try {
            return view('auth.login');
        } catch (\Exception $e) {
            logger()->error('Error en formulario login: ' . $e->getMessage());
            return redirect('/')->with('error', 'Error al cargar el formulario');
        }
    }

    /**
     * Procesar login
     */
    public function store(LoginFormRequest $request)
    {
        try {
            $credentials = $request->validated();

            // Usar AuthService para autenticar
            $user = $this->authService->authenticate($credentials['user'], $credentials['password']);

            if ($user) {
                Auth::login($user, false);
                $request->session()->regenerate();

                return response()->json([
                    'success' => true,
                    'message' => 'Sesión iniciada correctamente',
                    'redirect' => route('dashboard')
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Usuario o contraseña inválidos',
                'errors' => ['user' => ['Credenciales inválidas']]
            ], 422);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            
            // Mensajes específicos para cuentas desactivadas
            if (str_contains($message, 'desactivada') || str_contains($message, 'desactivado')) {
                Log::warning('Intento de acceso con cuenta desactivada: ' . $credentials['user']);
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => ['user' => [$message]]
                ], 403);
            }
            
            Log::error('Error en login: ' . $message);
            return response()->json([
                'success' => false,
                'message' => 'Error en el sistema',
                'errors' => ['system' => ['Error interno']]
            ], 500);
        }
    }

    /**
     * Cerrar sesión
     */
    public function destroy()
    {
        try {
            $userId = Auth::id();
            
            // Registrar log de logout
            if ($userId) {
                LogAcceso::create([
                    'usuario_id' => $userId,
                    'tipo_evento' => LogAcceso::EVENTO_LOGOUT,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'detalle' => 'Cierre de sesión',
                    'created_at' => now()
                ]);
            }
            
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
                'redirect' => route('login')
            ]);
        } catch (\Exception $e) {
            Log::error('Error en logout: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión'
            ], 500);
        }
    }
}
