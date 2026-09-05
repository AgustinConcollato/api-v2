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
            'distinctive_category_attribute_id' => 'nullable|integer|exists:category_attributes,id',
        ];
    }
}
