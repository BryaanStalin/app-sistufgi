<?php

namespace App\Http\Requests;

use App\Services\LaboratorioTomaMuestraService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaboratorioAsignacionContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return LaboratorioTomaMuestraService::puedeAcceder($this->user());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim(preg_replace('/\s+/u', ' ', $this->input('q')) ?? '')]);
        }
    }

    public function rules(): array
    {
        return [
            'campania_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:150'],
            'asignacion' => ['nullable', Rule::in(['todos', 'asignados', 'sin_asignar'])],
            'por_pagina' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'tabla' => ['nullable', 'boolean'],
            'exportar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda admite hasta 150 caracteres.',
            'asignacion.in' => 'Seleccione Todos, Asignados o Sin asignar.',
            'por_pagina.in' => 'Seleccione 25, 50 o 100 filas por página.',
        ];
    }
}
