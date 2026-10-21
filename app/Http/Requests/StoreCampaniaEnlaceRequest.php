<?php

namespace App\Http\Requests;

class StoreCampaniaEnlaceRequest extends OfertaFlexibleRequest
{
    public function rules(): array
    {
        return ['medico_usuario_id' => ['required', 'integer', 'exists:TB_ACCESO_USUARIO,id'],
            'admision_usuario_id' => ['required', 'integer', 'different:medico_usuario_id', 'exists:TB_ACCESO_USUARIO,id']];
    }
}
