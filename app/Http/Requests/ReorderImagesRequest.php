<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderImagesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'positions'            => 'required|array',
            'positions.*'          => 'required|array',
            'positions.*.id'       => 'required|integer|exists:images,id',
            'positions.*.position' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'positions.required'         => 'La lista de posiciones es obligatoria.',
            'positions.*.id.exists'      => 'Una de las IDs de imagen proporcionadas no existe.',
            'positions.*.position.min'   => 'La posición debe ser un número positivo.',
        ];
    }
}
