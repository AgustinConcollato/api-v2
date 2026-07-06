<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,credit_card,check',
            'note'           => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'         => 'Debes indicar el monto del pago.',
            'amount.numeric'          => 'El monto del pago debe ser un valor numérico.',
            'amount.min'              => 'El monto del pago debe ser al menos :min.',
            'payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_method.in'       => 'El método de pago seleccionado no es válido.',
        ];
    }
}
