<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddImagesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'images'     => 'required|array',
            'images.*'   => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Debes subir al menos una imagen para el producto.',
            'images.array'    => 'El campo de imágenes debe ser un conjunto de archivos.',
            'images.*.image'  => 'Uno de los archivos subidos no es una imagen válida.',
            'images.*.mimes'  => 'La imagen debe estar en formato PNG, JPG, JPEG o WEBP.',
            'images.*.max'    => 'La imagen no debe pesar más de 2MB (2048 KB).',
        ];
    }
}
