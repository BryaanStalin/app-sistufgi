<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'status' => 'required|boolean',
            'menus' => 'nullable|array',
            'menus.*' => 'integer|exists:TB_ACCESO_MENU,id',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El nombre del rol es requerido',
            'titulo.max' => 'El nombre del rol no puede superar los 255 caracteres',
            'status.required' => 'El estado es requerido',
            'menus.*.integer' => 'Seleccione una opción de menú válida',
            'menus.*.exists' => 'Seleccione una opción de menú válida',
        ];
    }
}
