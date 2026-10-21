<?php

namespace App\Http\Requests;

use App\Services\OfertaFlexibleAccess;
use App\Services\PacienteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OfertaFlexibleAccess::hasRole($this->user(), 'Médico Triaje');
    }

    public function rules(): array
    {
        $rules = ['accion' => ['required', Rule::in(['consultar', 'buscar', 'registrar', 'grabar'])],
            'campania_id' => ['required', 'integer', 'min:1'],
            'fecha_atencion' => ['prohibited'], 'medico_usuario_id' => ['prohibited'], 'hoja_ruta_id' => ['prohibited']];
        $accion = $this->input('accion');
        if (in_array($accion, ['buscar', 'registrar'], true)) {
            $rules['tipo_doc'] = ['required', Rule::in(PacienteService::TIPOS_DOCUMENTO)];
            $rules['dni'] = $this->input('tipo_doc') === 'S/ DOCUMENTO'
                ? ['exclude'] : ['required', 'string', 'max:50', ...($this->input('tipo_doc') === 'DNI' ? ['regex:/^[0-9]{8}$/'] : ['min:7'])];
        }
        if ($accion === 'registrar') {
            $rules += ['nombres_apellidos' => ['required', 'string', 'max:200'],
                'fecha_nacimiento' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today('America/Lima')->toDateString()],
                'sexo' => ['required', Rule::in(['M', 'F'])], 'telefono' => ['nullable', 'string', 'max:150']];
        }
        if (in_array($accion, ['consultar', 'grabar'], true)) {
            $rules['paciente_id'] = [$accion === 'grabar' ? 'required' : 'nullable', 'integer', 'min:1'];
        }
        if ($accion === 'grabar') {
            $rules += ['servicios' => ['required', 'array', 'min:1'], 'servicios.*' => ['required', 'integer', 'min:1', 'distinct']];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'in' => 'Seleccione una opción válida para :attribute.',
            'integer' => 'El campo :attribute no es válido.', 'array' => 'Seleccione una lista válida de servicios.',
            'min' => 'El campo :attribute debe tener como mínimo :min.', 'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'dni.regex' => 'El DNI debe tener exactamente 8 dígitos.', 'dni.min' => 'El documento debe tener al menos 7 caracteres.',
            'servicios.required' => 'Agregue al menos un servicio a la hoja de ruta.',
            'servicios.min' => 'Agregue al menos un servicio a la hoja de ruta.',
            'servicios.*.distinct' => 'No se puede agregar el mismo servicio dos veces.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'date_format' => 'Ingrese una fecha válida en formato AAAA-MM-DD.',
            'prohibited' => 'El campo :attribute lo determina el sistema y no se puede enviar.'];
    }

    public function attributes(): array
    {
        return ['dni' => 'documento', 'tipo_doc' => 'tipo de documento', 'nombres_apellidos' => 'nombres y apellidos',
            'fecha_nacimiento' => 'fecha de nacimiento', 'sexo' => 'sexo', 'telefono' => 'teléfono',
            'campania_id' => 'campaña', 'paciente_id' => 'paciente', 'servicios' => 'servicios'];
    }
}
