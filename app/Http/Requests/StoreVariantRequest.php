<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'              => 'required|string|max:100|unique:product_variants,sku',
            'stock'            => 'required|integer|min:0',
            'is_active'        => 'boolean',
            'attribute_values' => 'array',
            'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
            'attribute_values.*.value'                 => 'required|string|max:255',
        ];
    }
}
