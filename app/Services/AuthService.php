<?php

namespace App\Services;

use App\Models\User;
use App\Models\LogAcceso;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Autenticar usuario con usuario y contraseña
     */
    public function authenticate(string $usuario, string $contrasena): ?User
    {
        try {
            $user = User::where('user', $usuario)->first();

            if (!$user) {
                $this->logAccess(null, LogAcceso::EVENTO_LOGIN_FAILED, 'Usuario no existe: ' . $usuario);
                return null;
            }

            // Verificar contraseña
            if (!Hash::check($contrasena, $user->password)) {
                $this->logAccess($user->id, LogAcceso::EVENTO_LOGIN_FAILED, 'Contraseña incorrecta');
                return null;
            }

            // Verificar que usuario esté activo
            if ($user->status != 1) {
                $this->logAccess($user->id, LogAcceso::EVENTO_USUARIO_DESACTIVADO, 'Usuario desactivado');
                throw new \Exception('Tu cuenta ha sido desactivada. Contacta al administrador.');
            }

            // Verificar que tenga al menos un rol activo
            $rolActivo = $user->roles()
                ->where('TB_ACCESO_USUARIO_X_ROL.status', 1)
                ->where('TB_ACCESO_ROL.status', 1)
                ->exists();
                
            if (!$rolActivo) {
                $this->logAccess($user->id, LogAcceso::EVENTO_ROL_DESACTIVADO, 'Rol desactivado');
                throw new \Exception('Tu rol se encuentra desactivado. Contacta al administrador.');
            }

            // Log exitoso
            $this->logAccess($user->id, LogAcceso::EVENTO_LOGIN, 'Inicio de sesión exitoso');

            return $user;

        } catch (\Exception $e) {
            Log::error('Error en autenticación: ' . $e->getMessage());
            
            if (in_array($e->getMessage(), [
                'Tu cuenta ha sido desactivada. Contacta al administrador.',
                'Tu rol se encuentra desactivado. Contacta al administrador.'
            ])) {
                throw $e;
            }
            
            return null;
        }
    }
    
    
    /**
     * Registrar log de acceso
     */
    private function logAccess(?int $usuarioId, string $tipoEvento, string $detalle): void
    {
        try {
            LogAcceso::create([
                'usuario_id' => $usuarioId,
                'tipo_evento' => $tipoEvento,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'detalle' => $detalle,
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al registrar log de acceso: ' . $e->getMessage());
        }
    }
}
