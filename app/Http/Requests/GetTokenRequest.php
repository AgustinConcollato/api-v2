<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'No se recibió el código de autorización',
        ];
    }
}
