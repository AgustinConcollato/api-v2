<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'nullable|in:cash,transfer,credit_card,check',
            'note'           => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'       => 'El monto del pago es obligatorio.',
            'amount.min'            => 'El monto del pago debe ser mayor a 0.',
            'payment_date.required' => 'La fecha del pago es obligatoria.',
        ];
    }
}
