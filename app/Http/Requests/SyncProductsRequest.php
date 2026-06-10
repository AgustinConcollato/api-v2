<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncProductsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'products'                       => 'present|array',
            'products.*.id'                  => 'required|uuid|exists:products,id',
            'products.*.discount_type'       => 'nullable|in:percentage,fixed_amount,second_unit_percentage',
            'products.*.discount_value'      => 'nullable|numeric|min:0',
            'products.*.max_discount_amount' => 'nullable|numeric|min:0',
            'products.*.min_quantity'        => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'products.present'              => 'Debes enviar el campo products (puede ser array vacío para quitar todos).',
            'products.*.id.required'        => 'Cada producto debe tener un id.',
            'products.*.id.exists'          => 'Uno de los productos no existe.',
            'products.*.discount_type.in'   => 'El tipo de descuento debe ser: percentage, fixed_amount o second_unit_percentage.',
            'products.*.discount_value.min' => 'El valor de descuento debe ser al menos 0.',
            'products.*.min_quantity.min'   => 'La cantidad mínima debe ser al menos 1.',
        ];
    }
}
