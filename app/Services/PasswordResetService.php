<?php

namespace App\Services;

use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public const RESET_LINK_MESSAGE = 'Si el correo se encuentra registrado, recibirás un enlace para restablecer tu contraseña.';

    public const INVALID_RESET_MESSAGE = 'El enlace de recuperación no es válido o ha expirado. Solicita uno nuevo.';

    /**
     * @return array{success: bool, message: string}
     */
    public function sendResetLink(string $email): array
    {
        Password::sendResetLink(
            ['email' => Str::lower(trim($email)), 'status' => 1],
            function (User $user, string $token): void {
                $resetUrl = rtrim((string) config('app.url'), '/').route('password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                ], false);

                Mail::to($user->email, $user->nombre)
                    ->send(new PasswordResetLink($user->nombre, $resetUrl));
            }
        );

        return ['success' => true, 'message' => self::RESET_LINK_MESSAGE];
    }

    /**
     * @param  array{token: string, email: string, password: string}  $data
     * @return array{success: bool, status: int, message: string}
     */
    public function resetPassword(array $data): array
    {
        $status = Password::reset($data, function (User $user, string $password): void {
            DB::connection($user->getConnectionName())->transaction(function () use ($user, $password): void {
                $user->password = $password;
                $user->remember_token = Str::random(60);
                $user->save();

                if (config('session.driver') === 'database') {
                    DB::connection(config('session.connection'))
                        ->table(config('session.table', 'sessions'))
                        ->where('user_id', $user->getAuthIdentifier())
                        ->delete();
                }
            });

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return [
                'success' => true,
                'status' => 200,
                'message' => 'La contraseña se restableció correctamente.',
            ];
        }

        return [
            'success' => false,
            'status' => 422,
            'message' => self::INVALID_RESET_MESSAGE,
        ];
    }
}
