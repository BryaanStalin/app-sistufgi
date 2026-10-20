<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string'
            ],

            'email' => [
                'required',
                'email'
            ],

            'password' => [
                'required',
                'string',
                'confirmed'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'token.required' => 'El token es obligatorio.',
            'email.required' => 'El correo es obligatorio.'
        ];
    }
}
