<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreServicioUpsRequest extends OfertaFlexibleRequest
{
    public function rules(): array
    {
        return [
            'ID_UPS' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'DESCRIPCION_UPS' => ['required', 'string', 'max:255'],
            'TIPO_UPS' => ['required', Rule::in(['P', 'E'])],
            'ACTIVO' => ['required', 'boolean'],
        ];
    }
}
