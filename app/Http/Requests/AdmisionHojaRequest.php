<?php

namespace App\Http\Requests;

use App\Services\OfertaFlexibleAccess;
use Illuminate\Foundation\Http\FormRequest;

class AdmisionHojaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OfertaFlexibleAccess::puedeConsultarPacientes($this->user());
    }

    public function rules(): array
    {
        return [
            'campania_id' => ['required', 'integer', 'min:1'],
            'hoja_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute no es válido.',
            'min' => 'El campo :attribute no es válido.',
        ];
    }

    public function attributes(): array
    {
        return ['campania_id' => 'campaña', 'hoja_id' => 'hoja de ruta'];
    }
}
