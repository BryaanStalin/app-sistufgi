<?php

namespace App\Http\Requests;

use App\Services\OfertaFlexibleAccess;
use Illuminate\Foundation\Http\FormRequest;

class AdmisionListadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OfertaFlexibleAccess::hasRole($this->user(), 'Admisión');
    }

    public function rules(): array
    {
        return ['campania_id' => ['nullable', 'integer', 'min:1'], 'fecha' => ['nullable', 'date_format:Y-m-d'],
            'medico_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000']];
    }

    public function messages(): array
    {
        return ['integer' => 'Seleccione una opción válida para :attribute.', 'min' => 'El campo :attribute no es válido.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).', 'string' => 'Ingrese un texto válido para :attribute.',
            'date_format' => 'Ingrese una fecha válida en formato AAAA-MM-DD.'];
    }

    public function attributes(): array
    {
        return ['campania_id' => 'campaña', 'medico_id' => 'médico', 'q' => 'búsqueda', 'page' => 'página'];
    }
}
