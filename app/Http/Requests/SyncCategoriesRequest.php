<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncCategoriesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'categories'   => 'required|array',
            'categories.*' => 'exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'categories.required'   => 'Selecciona al menos una categoría.',
            'categories.*.exists'   => 'Una de las categorías seleccionadas no existe.',
        ];
    }
}
