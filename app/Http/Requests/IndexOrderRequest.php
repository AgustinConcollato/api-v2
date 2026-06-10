<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'status'     => 'nullable|string',
            'client_id'  => 'nullable|exists:clients,id',
            'range'      => 'nullable|string|in:week,month,all',
            'with_debt'  => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.date_format'  => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'end_date.date_format'    => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'client_id.exists'        => 'El cliente seleccionado no existe.',
            'range.in'                => 'El rango debe ser "week" (semana) o "month" (mes).',
        ];
    }
}
