<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Menu;
use App\Models\LogNavegacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LogNavigation
{
    /**
     * Lista de rutas que NO se deben registrar
     */
    private $excludedRoutes = [
        'login',
        'logout',
        'password',
        'api',
        '_debugbar',
        'sanctum',
    ];
    
    /**
     * Maneja la solicitud entrante
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        try {
            // Solo registrar si:
            // 1. El usuario está autenticado
            // 2. Es una petición GET (para evitar duplicados en POST/PUT/DELETE)
            // 3. No está en la lista de rutas excluidas
            if (Auth::check() && $request->isMethod('GET') && !$this->shouldExclude($request)) {
                $this->logNavigation($request);
            }
        } catch (\Exception $e) {
            // No bloquear la aplicación si falla el log
            Log::error('Error al registrar navegación: ' . $e->getMessage());
        }
        
        return $response;
    }
    
    /**
     * Determinar si la ruta debe ser excluida del log
     */
    private function shouldExclude(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->excludedRoutes as $excluded) {
            if (str_starts_with($path, $excluded)) {
                return true;
            }
        }
        
        // Excluir peticiones AJAX que no sean navegación
        if ($request->ajax() && !$request->headers->has('X-Navigation')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Registrar la navegación del usuario
     */
    private function logNavigation(Request $request): void
    {
        $ruta = $request->path();
        $rutaCompleta = '/' . trim($ruta, '/');
        
        // Buscar el menú correspondiente a la ruta
        $menu = $this->findMenuByRoute($rutaCompleta);
        
        // Solo registrar si encontramos un menú asociado
        if (!$menu) {
            // Para dashboard u otras rutas sin menú directo
            $menu = $this->findMenuByPartialRoute($rutaCompleta);
        }
        
        // Preparar parámetros (excluyendo datos sensibles)
        $parametros = $this->sanitizeParameters($request);
        
        LogNavegacion::create([
            'usuario_id' => Auth::id(),
            'menu_id' => $menu ? $menu->id : null,
            'ruta' => $rutaCompleta,
            'titulo_menu' => $menu ? $menu->titulo : $this->getTituloPorRuta($rutaCompleta),
            'metodo' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $this->truncateUserAgent($request->userAgent()),
            'parametros' => $parametros,
            'created_at' => now()
        ]);
    }
    
    /**
     * Buscar menú por ruta exacta o parcial
     */
    private function findMenuByRoute(string $ruta): ?Menu
    {
        try {
            // Buscar coincidencia exacta primero
            $menu = Menu::where('status', 1)
                ->where('link', $ruta)
                ->first();
                
            if ($menu) {
                return $menu;
            }
            
            // Buscar coincidencia parcial (para rutas con parámetros)
            return Menu::where('status', 1)
                ->where('link', 'LIKE', $ruta . '%')
                ->first();
                
        } catch (\Exception $e) {
            Log::error('Error al buscar menú por ruta exacta: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Buscar menú por coincidencia parcial más amplia
     */
    private function findMenuByPartialRoute(string $ruta): ?Menu
    {
        try {
            // Extraer el primer segmento de la ruta
            $segmentos = explode('/', trim($ruta, '/'));
            $primerSegmento = $segmentos[0] ?? '';
            
            if (empty($primerSegmento)) {
                return null;
            }
            
            return Menu::where('status', 1)
                ->where(function($query) use ($primerSegmento, $ruta) {
                    $query->where('link', 'LIKE', '%' . $primerSegmento . '%')
                          ->orWhere('link', 'LIKE', $ruta . '%');
                })
                ->orderByRaw('CASE 
                    WHEN link = ? THEN 1
                    WHEN link LIKE ? THEN 2
                    ELSE 3
                END', [$ruta, $ruta . '%'])
                ->first();
                
        } catch (\Exception $e) {
            Log::error('Error al buscar menú por ruta parcial: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sanitizar parámetros para el log
     */
    private function sanitizeParameters(Request $request): ?array
    {
        $parametros = $request->except([
            'password',
            'password_confirmation',
            '_token',
            '_method',
        ]);
        
        // Si no hay parámetros, retornar null
        if (empty($parametros)) {
            return null;
        }
        
        // Convertir a JSON para almacenar
        return $parametros;
    }
    
    /**
     * Truncar user agent para evitar exceder el límite
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }
        
        return substr($userAgent, 0, 500);
    }
    
    /**
     * Obtener título descriptivo para rutas sin menú
     */
    private function getTituloPorRuta(string $ruta): string
    {
        $titulos = [
            '/dashboard' => 'Dashboard Principal',
            '/admin/dashboard' => 'Panel de Administración',
        ];
        
        return $titulos[$ruta] ?? 'Navegación del Sistema';
    }
}