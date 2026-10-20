<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'link' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'orden' => 'required|integer|min:0',
            'padre_id' => 'nullable|integer|exists:TB_ACCESO_MENU,id',
            'status' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del menú es requerido',
            'titulo.max' => 'El título no puede superar los 255 caracteres',
            'link.required' => 'El enlace del menú es requerido',
            'link.max' => 'El enlace no puede superar los 255 caracteres',
            'orden.required' => 'El orden del menú es requerido',
            'orden.integer' => 'El orden debe ser un número entero',
            'padre_id.exists' => 'El menú padre seleccionado no es válido',
            'status.required' => 'El estado es requerido',
        ];
    }
}
