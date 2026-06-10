<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255|unique:suppliers,name',
            'contact_person' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255|unique:suppliers,email',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:512',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'name.unique'   => 'Ya existe un proveedor con este nombre.',
            'email.unique'  => 'Ya existe un proveedor con este correo electrónico.',
            'email.email'   => 'El formato del correo electrónico es inválido.',
        ];
    }
}
