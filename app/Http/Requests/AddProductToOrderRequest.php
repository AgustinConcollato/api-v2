<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddProductToOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'      => 'required|uuid|exists:products,id',
            'variant_id'      => 'nullable|integer|exists:product_variants,id',
            'quantity'        => 'required|integer|min:1',
            'unit_price'      => 'required|numeric|min:0',
            'purchase_price'  => 'required|numeric|min:0',
            'freight_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes especificar el producto que se está comprando.',
            'product_id.uuid'     => 'El identificador del producto no tiene un formato válido (UUID).',
            'product_id.exists'   => 'El producto especificado no existe en la base de datos.',
            'quantity.required'   => 'Debes indicar la cantidad de unidades del producto.',
            'quantity.integer'    => 'La cantidad debe ser un número entero.',
            'quantity.min'        => 'La cantidad mínima a ingresar es :min unidad.',
            'unit_price.required' => 'Debes ingresar el precio de venta unitario.',
            'unit_price.numeric'  => 'El precio de venta debe ser un valor numérico.',
            'unit_price.min'      => 'El precio de venta no puede ser negativo (mínimo :min).',
            'purchase_price.required' => 'Debes ingresar el precio de compra o costo unitario.',
            'purchase_price.numeric'  => 'El precio de compra debe ser un valor numérico.',
            'purchase_price.min'      => 'El precio de compra no puede ser negativo (mínimo :min).',
        ];
    }
}
