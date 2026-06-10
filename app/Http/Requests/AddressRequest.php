<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'label'         => 'nullable|string|max:255',
            'street'        => 'required|string|max:255',
            'street_number' => 'required|string|max:20',
            'floor'         => 'nullable|string|max:10',
            'apartment'     => 'nullable|string|max:10',
            'locality'      => 'required|string|max:255',
            'province'      => 'required|string|max:100',
            'postal_code'   => 'required|string|max:10',
            'is_default'    => 'nullable|boolean',
        ];
    }
}
