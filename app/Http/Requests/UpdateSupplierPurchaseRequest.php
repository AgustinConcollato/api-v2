<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'      => 'required|uuid|exists:suppliers,id',
            'invoice_number'   => 'nullable|string|max:255',
            'purchase_date'    => 'required|date',
            'due_date'         => 'nullable|date',
            'total'            => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'note'             => 'nullable|string|max:1000',
            'reminder_days'    => 'nullable|integer|min:0|max:365',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required'    => 'El proveedor es obligatorio.',
            'supplier_id.exists'      => 'El proveedor seleccionado no existe.',
            'purchase_date.required'  => 'La fecha de la compra es obligatoria.',
            'total.required'          => 'El total es obligatorio.',
            'total.min'               => 'El total no puede ser negativo.',
            'discount_percent.max'    => 'El descuento no puede superar el 100%.',
        ];
    }
}
