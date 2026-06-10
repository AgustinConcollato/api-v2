<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:text,number,select,boolean,combo',
            'required'   => 'boolean',
            'sort_order' => 'integer|min:0',
            'options'    => 'array',
            'options.*'  => 'string|max:150',
        ];
    }
}
