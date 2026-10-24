<?php

namespace App\Http\Requests;

use App\Services\LaboratorioAdmisionService;
use Illuminate\Validation\Rule;

class LaboratorioTomaMuestraRequest extends LaboratorioTomaMuestraContextRequest
{
    public function rules(): array
    {
        $rules = [
            'accion' => ['required', Rule::in(['buscar', 'grabar'])],
            'campania_id' => ['required', 'integer', 'min:1'],
            'tomado_by' => ['prohibited'],
            'tomado_at' => ['prohibited'],
            'fecha_atencion' => ['prohibited'],
        ];
        if ($this->input('accion') === 'buscar') {
            $rules += [
                'tipo_doc' => ['required', Rule::in(LaboratorioAdmisionService::DOCUMENTOS)],
                'dni' => [
                    'required', 'string', 'max:50',
                    ...($this->input('tipo_doc') === 'DNI' ? ['regex:/^[0-9]{8}$/'] : ['min:7']),
                ],
            ];
        }
        if ($this->input('accion') === 'grabar') {
            $rules += [
                'lab_paciente_id' => ['required', 'integer', 'min:1'],
                'estados' => ['required', 'array', 'min:1', 'max:200'],
                'estados.*' => ['required', 'array:id,estado,estado_original'],
                'estados.*.id' => ['required', 'integer', 'min:1', 'distinct'],
                'estados.*.estado' => ['required', Rule::in(['P', 'T', 'N'])],
                'estados.*.estado_original' => ['required', Rule::in(['P', 'T', 'N'])],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'dni.regex' => 'El DNI debe tener exactamente 8 dígitos.',
            'dni.min' => 'El documento debe tener al menos 7 caracteres.',
            'tipo_doc.in' => 'Seleccione DNI, CE, Pasaporte, DIE, CNV o S/ DOCUMENTO.',
            'estados.*.estado.in' => 'Seleccione Pendiente, Realizado o No realizado.',
            'estados.*.id.distinct' => 'No se puede enviar el mismo examen dos veces.',
            'prohibited' => 'El campo :attribute lo determina el servidor.',
        ];
    }
}
