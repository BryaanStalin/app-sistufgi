<?php

namespace App\Http\Requests;

class StoreCampaniaRequest extends OfertaFlexibleRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['servicios', 'laboratorio'] as $grupo) {
            if (! $this->has($grupo.'_config')) {
                continue;
            }
            $value = $this->input($grupo.'_config');
            $config = is_string($value) ? json_decode($value, true) : null;
            $this->merge([
                $grupo.'_generales' => is_array($config) ? $config['generales'] ?? null : null,
                $grupo.'_dia' => is_array($config) ? $config['diarios'] ?? null : null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'servicios_config' => ['sometimes', 'required', 'string', 'json', 'max:2097152'],
            'incluye_laboratorio' => ['sometimes', 'boolean'],
            'laboratorio_sobrescribir_personalizados' => ['sometimes', 'boolean'],
            'laboratorio_config' => ['sometimes', 'required', 'string', 'json', 'max:2097152'],
            'laboratorio_generales' => ['sometimes', 'array'],
            'laboratorio_generales.*' => ['array:ups_id,cupos'],
            'laboratorio_generales.*.ups_id' => ['required', 'integer', 'exists:TB_UPSS_CAMPAÑAS,id'],
            'laboratorio_generales.*.cupos' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'laboratorio_dia' => ['sometimes', 'array'],
            'laboratorio_dia.*' => ['array:fecha,ups_id,cupos'],
            'laboratorio_dia.*.fecha' => ['required', 'date_format:Y-m-d'],
            'laboratorio_dia.*.ups_id' => ['required', 'integer', 'exists:TB_UPSS_CAMPAÑAS,id'],
            'laboratorio_dia.*.cupos' => ['required', 'integer', 'min:1', 'max:2147483647'],
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
