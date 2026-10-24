<?php

namespace App\Http\Requests;

class LaboratorioEliminarRegistroRequest extends LaboratorioAdmisionContextRequest
{
    public function rules(): array
    {
        return [
            'campania_id' => ['required', 'integer', 'min:1'],
            'paciente_id' => ['required', 'integer', 'min:1'],
            'atencion_id' => ['required', 'integer', 'min:1'],
            'fecha_atencion' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'deleted_by' => ['prohibited'],
        ];
    }
}
