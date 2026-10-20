<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService
    ) {}

    /**
     * Mostrar formulario de recuperación de contraseña.
     */
    public function create(): View
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
                $request->validated()['email']
            );

            return response()->json($response);

        } catch (\Throwable) {

            logger()->error('Password reset link delivery failed.');

            return response()->json([
                'success' => true,
                'message' => PasswordResetService::RESET_LINK_MESSAGE,
            ]);

        }
    }

    /**
     * Mostrar formulario de restablecer la contraseña.
     */
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => is_string($request->query('email')) ? $request->query('email') : '',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
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
