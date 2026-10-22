<?php

namespace App\Http\Requests;

use App\Services\OfertaFlexibleAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class OfertaFlexibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OfertaFlexibleAccess::hasRole($this->user(), 'Administrador');
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute debe ser como mínimo :min.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'in' => 'El valor de :attribute no es válido.',
            'exists' => 'El registro seleccionado para :attribute no existe.',
            'date_format' => 'El campo :attribute debe tener formato AAAA-MM-DD.',
            'after_or_equal' => 'La fecha fin debe ser igual o posterior a la fecha inicio.',
            'array' => 'El campo :attribute debe contener una lista válida.',
            'boolean' => 'Seleccione Sí o No para :attribute.',
            'distinct' => 'No se pueden repetir los usuarios seleccionados.',
            'accepted' => 'Debe confirmar la operación.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'fecha_inicio' => 'fecha inicio',
            'fecha_fin' => 'fecha fin',
            'TIPO_UPS' => 'tipo UPSS',
            'ID_UPS' => 'código UPSS',
            'DESCRIPCION_UPS' => 'descripción UPSS',
            'servicios_generales.*.cupos' => 'cupo general',
            'servicios_dia.*.cupos' => 'cupo diario',
            'usuarios' => 'usuarios',
            'confirmar' => 'confirmación',
        ];
    }
}
