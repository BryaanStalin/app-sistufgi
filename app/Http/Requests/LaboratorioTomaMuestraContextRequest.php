<?php

namespace App\Http\Requests;

use App\Services\LaboratorioTomaMuestraService;
use Illuminate\Foundation\Http\FormRequest;

class LaboratorioTomaMuestraContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return LaboratorioTomaMuestraService::puedeAcceder($this->user());
    }

    public function rules(): array
    {
        return [
            'campania_id' => ['nullable', 'integer', 'min:1'],
            'stock' => ['nullable', 'boolean'],
        ];
    }
}
