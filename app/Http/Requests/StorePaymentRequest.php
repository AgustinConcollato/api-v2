<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'order_id'       => 'required|uuid|exists:orders,id',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,credit_card,check',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required'       => 'Indica de qué pedido es el pago.',
            'order_id.uuid'           => 'El identificador del pedido no tiene un formato válido (UUID).',
            'order_id.exists'         => 'El pedido especificado no existe.',
            'amount.required'         => 'Debes indicar el monto del pago.',
            'amount.numeric'          => 'El monto del pago debe ser un valor numérico.',
            'amount.min'              => 'El monto del pago debe ser al menos :min.',
            'payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_method.in'       => 'El método de pago seleccionado no es válido.',
        ];
    }
}
