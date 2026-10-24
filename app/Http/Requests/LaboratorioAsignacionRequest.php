<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class LaboratorioAsignacionRequest extends LaboratorioAsignacionContextRequest
{
    public function rules(): array
    {
        return [
            'campania_id' => ['required', 'integer', 'min:1'],
            'pacientes' => ['required', 'array', 'min:1', 'max:100'],
            'pacientes.*' => ['required', 'integer', 'min:1', 'distinct'],
            'renipress' => [
                'required', 'string', Rule::in(array_column(config('oferta_flexible.laboratorio.eess'), 'renipress')),
            ],
            'eess_asignado_at' => ['prohibited'],
            'eess_asignado_by' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'pacientes.required' => 'Seleccione al menos un paciente.',
            'pacientes.*.distinct' => 'No se puede enviar el mismo paciente dos veces.',
            'pacientes.max' => 'Seleccione como máximo 100 pacientes por operación.',
            'renipress.required' => 'Seleccione el laboratorio de destino.',
            'renipress.in' => 'Seleccione uno de los EESS configurados.',
            'prohibited' => 'El usuario y la fecha de asignación los determina el servidor.',
        ];
    }
}
