<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderDetailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'quantity'              => 'nullable|integer|min:1',
            'unit_price'            => 'nullable|numeric|min:0',
            'discount_percentage'   => 'nullable|numeric|min:0|max:100',
            'discount_fixed_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.integer'           => 'La cantidad debe ser un número entero.',
            'quantity.min'               => 'La cantidad mínima debe ser :min unidad.',
            'unit_price.numeric'         => 'El precio de venta debe ser un valor numérico.',
            'unit_price.min'             => 'El precio de venta no puede ser negativo (mínimo :min).',
            'discount_percentage.numeric'   => 'El porcentaje de descuento debe ser un valor numérico.',
            'discount_percentage.min'       => 'El porcentaje de descuento mínimo debe ser :min.',
            'discount_percentage.max'       => 'El porcentaje de descuento máximo permitido es :max.',
            'discount_fixed_amount.numeric' => 'El monto de descuento fijo debe ser un valor numérico.',
            'discount_fixed_amount.min'     => 'El monto de descuento fijo no puede ser negativo (mínimo :min).',
        ];
    }
}
