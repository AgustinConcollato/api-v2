<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterFromOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'El identificador del pedido es obligatorio.',
            'order_id.uuid'     => 'El formato del identificador de pedido no es válido.',
            'order_id.exists'   => 'El pedido seleccionado no existe en nuestro sistema.',
            'password.required' => 'Es necesario que establezcas una contraseña para crear tu cuenta.',
            'password.string'   => 'La contraseña debe ser una cadena de texto válida.',
            'password.min'      => 'Tu contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
