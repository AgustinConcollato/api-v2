<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'              => 'required|string|max:100|unique:product_variants,sku,' . $this->route('variant')->id,
            'stock'            => 'required|integer|min:0',
            'is_active'        => 'boolean',
            'name'             => 'nullable|string|max:255',
            'is_dropshipping'  => 'nullable|boolean',
            'attribute_values' => 'array',
            'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
            'attribute_values.*.value'                 => 'required|string|max:255',
            'prices'                     => 'array',
            'prices.*.price_list_id'     => 'required|integer|exists:price_lists,id',
            'prices.*.price'             => 'required|numeric|min:0',
        ];
    }
}
