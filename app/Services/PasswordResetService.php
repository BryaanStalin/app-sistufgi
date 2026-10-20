<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class PasswordResetService
{
    /**
     * Enviar enlace de recuperación.
     */
    public function sendResetLink(string $username): array
    {
        $user = $this->findActiveUserByUsername($username);
        $message = 'Si el usuario se encuentra registrado, recibirás un enlace para restablecer tu contraseña.';

        if (!$user) {

            return [
                'success' => true,
                'message' => $message
            ];

        }

        /*
            Generar token de recuperación
        */

        $token = Password::broker()
            ->getRepository()
            ->create($user);

        /*
            Generar URL de recuperación
        */

        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email
        ]);

        Mail::send(
            'emails.password-reset',
            [
                'nombre' => $user->nombre,
                'url' => $resetUrl
            ],
            function ($message) use ($user) {

                $message->to(
                    $user->email,
                    $user->nombre
                )
                ->subject('Recuperación de contraseña');

            }
        );

        logger()->info('URL recuperación generada', [
            'usuario' => $user->user,
            'email' => $user->email,
            'url' => $resetUrl
        ]);

        return [
            'success' => true,
            'message' => $message
        ];
    }

    /**
     * Restablecer la contraseña.
     */
    public function resetPassword(array $data): array
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {

                /*
                    Actualizar contraseña
                */

                $user->password = $password;

                /*
                    Invalidar remember token
                */

                $user->remember_token = Str::random(60);

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'La contraseña se restableció correctamente.'
            ];
        }

        return [
            'success' => false,
            'status' => 422,
            'message' => __($status)
        ];
    }

    /**
     * Buscar usuario activo por nombre de usuario.
     */
    private function findActiveUserByUsername(string $username): ?User
    {
        return User::query()
            ->where('user', $username)
            ->where('status', 1)
            ->first();
    }
}
