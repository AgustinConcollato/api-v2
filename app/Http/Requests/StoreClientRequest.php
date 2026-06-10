<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|unique:clients,email',
            'phone'         => 'nullable|string|max:20|regex:/^[0-9\s\-\+()]*$/',
            'price_list_id' => 'required|exists:price_lists,id',
            'password'      => [
                'required',
                Password::default(8)->letters()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'El nombre es obligatorio',
            'email.email'            => 'El email no es válido',
            'email.unique'           => 'El email ya está registrado',
            'password.required'      => 'La contraseña es obligatoria',
            'password.letters'       => 'La contraseña debe contener al menos una letra.',
            'password.numbers'       => 'La contraseña debe contener al menos un número.',
            'password.min'           => 'La contraseña debe tener al menos 8 caracteres',
            'price_list_id.required' => 'La lista de precios es obligatoria',
            'price_list_id.exists'   => 'La lista de precios no existe',
            'phone.max'              => 'El teléfono no puede exceder 20 caracteres.',
            'phone.regex'            => 'El formato del teléfono no es válido. Solo se permiten números, espacios, guiones y el signo "+".',
        ];
    }
}
