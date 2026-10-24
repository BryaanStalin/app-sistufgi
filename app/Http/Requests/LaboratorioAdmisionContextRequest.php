<?php

namespace App\Http\Requests;

use App\Services\LaboratorioAdmisionService;
use Illuminate\Foundation\Http\FormRequest;

class LaboratorioAdmisionContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return LaboratorioAdmisionService::puedeAcceder($this->user());
    }

    public function rules(): array
    {
        return [
            'campania_id' => ['nullable', 'integer', 'min:1'],
            'stock' => ['nullable', 'boolean'],
        ];
    }
}
