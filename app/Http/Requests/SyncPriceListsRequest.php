<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncPriceListsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'price_list_ids'   => 'required|array',
            'price_list_ids.*' => 'integer|exists:price_lists,id',
        ];
    }

    public function messages(): array
    {
        return [
            'price_list_ids.required' => 'Debes enviar el array de listas (puede ser vacío).',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
        ];
    }
}
