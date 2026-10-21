<?php

namespace App\Http\Requests;

class StoreCampaniaUsuarioRequest extends OfertaFlexibleRequest
{
    public function rules(): array
    {
        return ['usuarios' => ['required', 'array', 'min:1'],
            'usuarios.*' => ['required', 'integer', 'distinct', 'exists:TB_ACCESO_USUARIO,id']];
    }
}
