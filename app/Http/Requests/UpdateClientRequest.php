<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id;

        return [
            'name'          => 'nullable|string|max:255',
            'email'         => "nullable|email|unique:clients,email,{$clientId}",
            'phone'         => 'nullable|string|max:20|regex:/^[0-9\s\-\+()]*$/',
            'price_list_id' => 'nullable|exists:price_lists,id',
            'password'      => [
                'nullable',
                Password::default(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'          => 'El email no es válido.',
            'email.unique'         => 'El email ya está registrado.',
            'price_list_id.exists' => 'La lista de precios no existe.',
            'phone.max'            => 'El teléfono no puede exceder 20 caracteres.',
            'phone.regex'          => 'El formato del teléfono no es válido. Solo se permiten números, espacios, guiones y el signo "+".',
        ];
    }
}
