<?php

namespace App\Http\Requests;

class StoreCampaniaRequest extends OfertaFlexibleRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('servicios_config')) {
            return;
        }
        $value = $this->input('servicios_config');
        $config = is_string($value) ? json_decode($value, true) : null;
        $this->merge([
            'servicios_generales' => is_array($config) ? $config['generales'] ?? null : null,
            'servicios_dia' => is_array($config) ? $config['diarios'] ?? null : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'servicios_config' => ['sometimes', 'required', 'string', 'json', 'max:2097152'],
            'nombre' => ['required', 'string', 'max:200'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'asociar_triaje_admision' => ['required', 'boolean'],
            'mismos_servicios' => ['required', 'boolean'],
            'sobrescribir_personalizados' => ['sometimes', 'boolean'],
            'servicios_generales' => ['required_if:mismos_servicios,1', 'array'],
            'servicios_generales.*' => ['array:ups_id,cupos'],
            'servicios_generales.*.ups_id' => ['required', 'integer', 'exists:TB_UPSS_CAMPAÑAS,id'],
            'servicios_generales.*.cupos' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'servicios_dia' => ['required_if:mismos_servicios,0', 'array'],
            'servicios_dia.*' => ['array:fecha,ups_id,cupos'],
            'servicios_dia.*.fecha' => ['required', 'date_format:Y-m-d'],
            'servicios_dia.*.ups_id' => ['required', 'integer', 'exists:TB_UPSS_CAMPAÑAS,id'],
            'servicios_dia.*.cupos' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
