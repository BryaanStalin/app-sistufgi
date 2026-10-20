<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'link' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'orden' => 'required|integer|min:0',
            'padre_id' => 'nullable|integer|exists:TB_ACCESO_MENU,id',
            'status' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del menú es requerido',
            'titulo.max' => 'El título no puede superar los 255 caracteres',
            'link.required' => 'El enlace del menú es requerido',
            'link.max' => 'El enlace no puede superar los 255 caracteres',
            'orden.required' => 'El orden del menú es requerido',
            'orden.integer' => 'El orden debe ser un número entero',
            'padre_id.exists' => 'El menú padre seleccionado no es válido',
            'status.required' => 'El estado es requerido',
        ];
    }

    /** @return array<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('padre_id') || ! $this->filled('padre_id')) {
                return;
            }

            $menu = $this->route('menu');
            $parentId = $this->integer('padre_id');
            $visited = [];

            while ($parentId !== null) {
                if ((int) $menu->id === (int) $parentId || isset($visited[$parentId])) {
                    $validator->errors()->add('padre_id', 'El menú padre no puede ser el mismo menú ni uno de sus descendientes.');

                    return;
                }
                $visited[$parentId] = true;
                $parentId = Menu::query()->whereKey($parentId)->value('padre_id');
            }
        }];
    }
}
