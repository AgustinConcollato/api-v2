<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'price_lists'           => 'required|array',
            'price_lists.*.list_id' => 'required|exists:price_lists,id',
            'price_lists.*.price'   => 'required|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'price_lists.required'          => 'La lista de precios es obligatoria.',
            'price_lists.*.list_id.exists'  => 'El ID de uno de las listas no es válido.',
            'price_lists.*.price.required'  => 'El precio es obligatorio para cada lista de precio.',
            'price_lists.*.price.numeric'   => 'El precio de venta debe ser un número.',
            'price_lists.*.price.min'       => 'El precio debe ser mayor a 0.',
        ];
    }
}
