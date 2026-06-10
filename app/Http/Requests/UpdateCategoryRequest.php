<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255|unique:categories,name,' . $this->route('category')->id,
            'parent_id' => 'nullable|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'El nombre de la categoría es obligatorio.',
            'name.string'      => 'El nombre debe ser una cadena de texto válida.',
            'name.max'         => 'El nombre no puede exceder los 255 caracteres.',
            'name.unique'      => 'Este nombre de categoría ya existe. Por favor, elija otro.',
            'parent_id.exists' => 'La categoría padre seleccionada no es válida o no existe.',
        ];
    }
}
