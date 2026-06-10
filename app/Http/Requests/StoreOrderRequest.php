<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id'                          => 'nullable|uuid|exists:clients,id',
            'shipping_address'                   => 'nullable|array',
            'shipping_address.street'            => 'required_with:shipping_address|string|max:255',
            'shipping_address.street_number'     => 'required_with:shipping_address|string|max:20',
            'shipping_address.floor'             => 'nullable|string|max:10',
            'shipping_address.apartment'         => 'nullable|string|max:10',
            'shipping_address.locality'          => 'required_with:shipping_address|string|max:255',
            'shipping_address.province'          => 'required_with:shipping_address|string|max:100',
            'shipping_address.postal_code'       => 'required_with:shipping_address|string|max:10',
            'price_list_id'                      => 'required|integer|exists:price_lists,id',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.uuid'                              => 'El identificador del cliente no tiene un formato válido (UUID).',
            'client_id.exists'                            => 'El cliente especificado no existe en la base de datos.',
            'price_list_id.exists'                        => 'La lista de precios especificada no existe en la base de datos.',
            'price_list_id.required'                      => 'La lista de precios es obligatoria.',
            'shipping_address.street.required_with'       => 'La calle es obligatoria.',
            'shipping_address.street_number.required_with'=> 'El número es obligatorio.',
            'shipping_address.locality.required_with'     => 'La localidad es obligatoria.',
            'shipping_address.province.required_with'     => 'La provincia es obligatoria.',
            'shipping_address.postal_code.required_with'  => 'El código postal es obligatorio.',
        ];
    }
}
