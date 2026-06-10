<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'starts_at'          => 'nullable|date',
            'ends_at'            => 'nullable|date|after_or_equal:starts_at',
            'is_active'          => 'nullable|boolean',
            'discount_type'      => 'required|in:percentage,fixed_amount,second_unit_percentage',
            'discount_value'     => 'required|numeric|min:0',
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
            'name.required'              => 'El nombre de la promoción es obligatorio.',
            'discount_type.required'     => 'El tipo de descuento es obligatorio.',
            'discount_type.in'           => 'El tipo de descuento debe ser: percentage, fixed_amount o second_unit_percentage.',
            'discount_value.required'    => 'El valor del descuento es obligatorio.',
            'product_ids.*.exists'       => 'Uno de los productos no existe.',
            'price_list_ids.*.exists'    => 'Una de las listas de precios no existe.',
            'starts_at.date'             => 'La fecha de inicio debe ser una fecha válida.',
            'ends_at.date'               => 'La fecha de fin debe ser una fecha válida.',
            'ends_at.after_or_equal'     => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'is_active.boolean'          => 'El campo is_active debe ser true (1) o false (0).',
            'min_quantity.integer'       => 'La cantidad mínima debe ser un número entero.',
            'min_quantity.min'           => 'La cantidad mínima debe ser al menos 1.',
            'max_discount_amount.numeric'=> 'El tope de descuento debe ser un número.',
            'max_discount_amount.min'    => 'El tope de descuento debe ser al menos 0.',
        ];
    }
}
