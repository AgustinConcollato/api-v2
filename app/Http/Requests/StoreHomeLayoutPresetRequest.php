<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeLayoutPresetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'sections' => 'present|array',
            ...UpdateHomeLayoutDraftRequest::sectionsRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'El diseño necesita un nombre.',
            'sections.present'  => 'Las secciones son obligatorias.',
            ...UpdateHomeLayoutDraftRequest::sectionsMessages(),
        ];
    }
}
