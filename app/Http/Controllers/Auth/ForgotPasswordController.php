<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService
    ) {}

    /**
     * Mostrar formulario de recuperación de contraseña.
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Procesar solicitud de recuperación.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        try {

            $response = $this->passwordResetService->sendResetLink(
                $request->validated()['user']
            );

            return response()->json($response);

        } catch (\Throwable $e) {

            logger()->error('Error al solicitar recuperación de contraseña: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del sistema.',
                'errors' => [
                    'system' => [
                        'Ocurrió un error inesperado.'
                    ]
                ]
            ], 500);

        }
    }

    /**
     * Mostrar formulario de restablecer la contraseña.
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
            'user' => User::where('email', $request->email)->value('user')
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $resultado = $this->passwordResetService->resetPassword(
            $request->validated()
        );

        return response()->json(
            $resultado,
            $resultado['status']
        );
    }
}
