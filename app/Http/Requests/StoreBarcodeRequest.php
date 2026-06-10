<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarcodeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'barcode'    => 'required|string|max:255',
            'is_primary' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.required' => 'El código de barras es obligatorio.',
        ];
    }
}
