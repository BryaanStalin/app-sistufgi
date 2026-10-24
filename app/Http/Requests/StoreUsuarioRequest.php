<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:TB_ACCESO_USUARIO,email',
            'user' => 'required|string|max:120|unique:TB_ACCESO_USUARIO,user',
            'password' => ['required', 'string',  'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'DNI' => 'required|string|max:12',
            'status' => 'required|boolean',
            'role' => 'required|integer|exists:TB_ACCESO_ROL,id',
            'eess' => 'nullable|string|max:100|exists:TB_EESS,RENIPRESS',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido',
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'El email ya está registrado',
            'user.required' => 'El usuario es requerido',
            'user.unique' => 'El nombre de usuario ya existe',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser un texto.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.mixed' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.confirmed' => 'La confirmación de contraseña no coincide',
            'password_confirmation.required' => 'La confirmación de contraseña es obligatoria.',
            'status.required' => 'El estado es requerido',
            'role.required' => 'Debe asignarse un rol',
            'eess.exists' => 'El establecimiento seleccionado no es válido',
        ];
    }
}
