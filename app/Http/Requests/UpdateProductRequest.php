<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => 'nullable|string|max:255',
            'description'     => 'nullable|string',
            'stock'           => 'nullable|integer|min:0',
            'is_dropshipping' => 'nullable|boolean',
        ];
    }
}
