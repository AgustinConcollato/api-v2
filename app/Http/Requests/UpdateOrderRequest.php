<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id'             => 'nullable|uuid|exists:clients,id',
            'status'                => 'nullable|in:pending,confirmed,shipped,processing,cancelled,delivered',
            'discount_percentage'   => 'nullable|numeric|min:0|max:100',
            'discount_fixed_amount' => 'nullable|numeric|min:0',
            'shipping_cost'         => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.uuid'                   => 'El identificador del cliente no tiene un formato válido (UUID).',
            'client_id.exists'                 => 'El cliente especificado no existe en la base de datos.',
            'status.in'                        => 'El estado de la orden no es válido.',
            'discount_percentage.numeric'      => 'El porcentaje de descuento debe ser un valor numérico.',
            'discount_percentage.min'          => 'El porcentaje de descuento mínimo debe ser :min.',
            'discount_percentage.max'          => 'El porcentaje de descuento máximo permitido es :max.',
            'discount_fixed_amount.numeric'    => 'El monto de descuento fijo debe ser un valor numérico.',
            'discount_fixed_amount.min'        => 'El monto de descuento fijo no puede ser negativo (mínimo :min).',
            'shipping_cost.numeric'            => 'El costo de envío debe ser un valor numérico.',
            'shipping_cost.min'                => 'El costo de envío no puede ser negativo (mínimo :min).',
        ];
    }
}
