<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'is_active' => 'nullable|boolean',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'is_active.boolean' => 'El campo is_active debe ser true (1) o false (0).',
            'per_page.integer'  => 'El campo per_page debe ser un número entero.',
            'per_page.min'      => 'El campo per_page debe ser al menos 1.',
            'per_page.max'      => 'El campo per_page no puede ser mayor a 100.',
        ];
    }
}
