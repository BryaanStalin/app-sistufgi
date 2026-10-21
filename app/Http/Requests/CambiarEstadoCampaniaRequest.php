<?php

namespace App\Http\Requests;

class CambiarEstadoCampaniaRequest extends OfertaFlexibleRequest
{
    public function rules(): array
    {
        return ['confirmar' => ['required', 'accepted']];
    }
}
