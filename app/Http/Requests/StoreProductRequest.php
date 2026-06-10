<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                                      => 'required|string|max:255',
            'description'                               => 'nullable|string',
            'stock'                                     => 'nullable|integer|min:0',
            'images'                                    => 'required|array',
            'images.*'                                  => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'image_positions'                           => 'nullable|array',
            'image_positions.*'                         => 'required|integer|min:0',
            'categories'                                => 'nullable|array',
            'categories.*'                              => 'exists:categories,id',
            'attribute_values'                          => 'nullable|array',
            'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
            'attribute_values.*.value'                  => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'El nombre del producto es obligatorio.',
            'name.max'             => 'El nombre no puede exceder 255 caracteres.',
            'stock.integer'        => 'El stock debe ser un número entero.',
            'stock.min'            => 'El stock no puede ser negativo.',
            'sku.unique'           => 'El código SKU ya está registrado.',
            'images.required'      => 'Ingresa una imagen.',
            'images.*.max'         => 'La imagen no debe pesar más de 2MB.',
            'images.*.mimes'       => 'La imagen debe estar en formato PNG, JPG, JPEG o WEBP.',
            'categories.*.exists'  => 'Una de las categorías seleccionadas no existe.',
        ];
    }
}
