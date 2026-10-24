<?php

namespace App\Http\Requests;

use App\Services\LaboratorioAdmisionService;
use Illuminate\Validation\Rule;

class LaboratorioAdmisionRequest extends LaboratorioAdmisionContextRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('telefono'))) {
            $partes = preg_split('/[\s\/,]+/u', trim($this->input('telefono')), -1, PREG_SPLIT_NO_EMPTY);
            $this->merge(['telefono' => implode(' / ', $partes ?: [])]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'accion' => ['required', Rule::in(['buscar', 'validar', 'grabar'])],
            'campania_id' => ['required', 'integer', 'min:1'],
            'fecha_atencion' => ['prohibited'],
            'tm_numero' => ['prohibited'],
            'estado' => ['prohibited'],
            'created_by' => ['prohibited'],
            'hoja_ruta_id' => ['prohibited'],
        ];
        if ($this->input('accion') === 'buscar') {
            $rules['tipo_doc'] = ['required', Rule::in(LaboratorioAdmisionService::DOCUMENTOS)];
            $rules['dni'] = ['required', 'string', 'max:50',
                ...($this->input('tipo_doc') === 'DNI' ? ['regex:/^[0-9]{8}$/'] : ['min:7']),
            ];
        }
        if (in_array($this->input('accion'), ['validar', 'grabar'], true)) {
            $rules += [
                'paciente_id' => ['required', 'integer', 'min:1'],
                'examenes' => ['present', 'array', 'max:200'],
                'examenes.*' => ['integer', 'min:1', 'distinct'],
                'eliminar' => ['sometimes', 'array', 'max:200'],
                'eliminar.*' => ['integer', 'min:1', 'distinct'],
            ];
        }
        if ($this->input('accion') === 'grabar') {
            $rules += [
                'fua' => ['required', 'string', 'max:20', 'regex:/^[0-9-]+$/'],
                'ups_id_solicita' => ['required', 'integer', 'min:1'],
                'telefono' => ['required', 'string', 'max:21', 'regex:/^[0-9]{9}( \/ [0-9]{9})?$/'],
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
            'fua.regex' => 'El N° FUA solo acepta números y guiones.',
            'telefono.regex' => 'Ingrese uno o dos celulares de 9 dígitos, separados por /, coma o espacio.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'distinct' => 'No se puede agregar el mismo examen dos veces.',
            'date_format' => 'Ingrese una fecha válida en formato AAAA-MM-DD.',
            'before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'prohibited' => 'El campo :attribute lo determina el servidor.',
        ];
    }
}
