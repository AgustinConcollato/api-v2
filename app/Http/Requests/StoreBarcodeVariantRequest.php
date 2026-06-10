<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarcodeVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => 'required|string|max:255|unique:product_barcodes,barcode',
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.required' => 'El código de barras es obligatorio.',
            'barcode.unique'   => 'Este código de barras ya está registrado.',
        ];
    }
}
