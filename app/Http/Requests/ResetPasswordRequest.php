<?php

namespace App\Http\Requests;

use App\Services\PasswordResetService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['email', 'token'])) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'status' => 422,
                'message' => PasswordResetService::INVALID_RESET_MESSAGE,
            ], 422));
        }

        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
            ],

            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
                Password::defaults(),
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser un texto.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.mixed' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'token.required' => 'El token es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
        ];
    }
}
