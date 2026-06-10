<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttributeValuesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'attribute_values'                          => 'required|array',
            'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
            'attribute_values.*.value'                  => 'required|string|max:255',
        ];
    }
}
