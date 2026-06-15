<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'starts_at'          => 'nullable|date',
            'ends_at'            => 'nullable|date',
            'is_active'          => 'nullable|boolean',
            'show_on_web'        => 'nullable|boolean',
            'discount_type'      => 'nullable|in:percentage,fixed_amount,second_unit_percentage',
            'discount_value'     => 'nullable|numeric|min:0',
            'max_discount_amount'=> 'nullable|numeric|min:0',
            'min_quantity'       => 'nullable|integer|min:1',
            'product_ids'        => 'nullable|array',
            'product_ids.*'      => 'uuid|exists:products,id',
            'price_list_ids'     => 'nullable|array',
            'price_list_ids.*'   => 'integer|exists:price_lists,id',
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.*.exists'    => 'Uno de los productos no existe.',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
        ];
    }
}
