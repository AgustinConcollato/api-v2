<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompareMonthsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month_a' => 'nullable|integer|min:1|max:12',
            'year_a'  => 'nullable|integer|min:2000',
            'month_b' => 'nullable|integer|min:1|max:12',
            'year_b'  => 'nullable|integer|min:2000',
        ];
    }
}
