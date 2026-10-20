<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación.
     */
    public function rules(): array
    {
        return [
            'user' => [
                'required',
                'string',
                'max:120',
            ],
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function messages(): array
    {
        return [
            'user.required' => 'El usuario es requerido.',
            'user.string' => 'El usuario debe ser texto.',
            'user.max' => 'El usuario no puede exceder los 120 caracteres.',
        ];
    }
}
