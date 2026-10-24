<?php

namespace App\Http\Requests;

class LaboratorioStockRequest extends OfertaFlexibleRequest
{
    public function rules(): array
    {
        return ['fecha' => ['nullable', 'date_format:Y-m-d']];
    }
}
