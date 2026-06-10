<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                               => 'required|string|max:255',
            'email'                              => 'required|email|max:255',
            'phone'                              => 'required|string|max:50',
            'delivery_method'                    => 'required|in:shipping,whatsapp',
            'shipping_address'                   => 'required_if:delivery_method,shipping|nullable|array',
            'shipping_address.street'            => 'required_if:delivery_method,shipping|nullable|string|max:255',
            'shipping_address.street_number'     => 'required_if:delivery_method,shipping|nullable|string|max:20',
            'shipping_address.floor'             => 'nullable|string|max:10',
            'shipping_address.apartment'         => 'nullable|string|max:10',
            'shipping_address.locality'          => 'required_if:delivery_method,shipping|nullable|string|max:255',
            'shipping_address.province'          => 'required_if:delivery_method,shipping|nullable|string|max:100',
            'shipping_address.postal_code'       => 'required_if:delivery_method,shipping|nullable|string|max:10',
            'notes'                              => 'nullable|string|max:1000',
            'items'                              => 'required|array|min:1',
            'items.*.product_id'                 => 'required|string',
            'items.*.variant_id'                 => 'nullable|integer',
            'items.*.quantity'                   => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                              => 'El nombre es obligatorio.',
            'name.max'                                   => 'El nombre no puede exceder los 255 caracteres.',
            'email.required'                             => 'El correo electrónico es obligatorio.',
            'email.email'                                => 'El formato del correo no es válido.',
            'phone.required'                             => 'El teléfono es obligatorio.',
            'delivery_method.required'                   => 'Debe seleccionar un método de entrega.',
            'delivery_method.in'                         => 'El método de entrega seleccionado no es válido.',
            'shipping_address.required_if'               => 'La dirección de envío es obligatoria.',
            'shipping_address.street.required_if'        => 'La calle es obligatoria.',
            'shipping_address.street_number.required_if' => 'El número de calle es obligatorio.',
            'shipping_address.locality.required_if'      => 'La localidad es obligatoria.',
            'shipping_address.province.required_if'      => 'La provincia es obligatoria.',
            'shipping_address.postal_code.required_if'   => 'El código postal es obligatorio.',
            'items.required'                             => 'Debes agregar al menos un producto a la orden.',
            'items.min'                                  => 'La orden debe contener como mínimo :min ítem.',
            'items.*.product_id.required'                => 'El ID del producto es obligatorio.',
            'items.*.quantity.required'                  => 'La cantidad es obligatoria.',
            'items.*.quantity.integer'                   => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min'                       => 'La cantidad mínima por producto debe ser :min.',
        ];
    }
}
