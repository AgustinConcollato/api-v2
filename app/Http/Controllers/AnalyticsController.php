<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnalyticsController
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Devuelve un resumen con métricas agregadas de pedidos.
     */
    public function overview(Request $request)
    {
        $rules = [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'range' => 'nullable|in:week,month,all,custom',
        ];

        $messages = [
            'start_date.date_format' => 'La fecha de inicio debe tener el formato AAAA-MM-DD.',
            'end_date.date_format' => 'La fecha de fin debe tener el formato AAAA-MM-DD.',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'client_id.exists' => 'El cliente especificado no existe en la base de datos.',
            'range.in' => 'El rango debe ser "week", "month", "all" o "custom".',
        ];

        try {
            $validated = $request->validate($rules, $messages);
            $data = $this->analyticsService->getOverview($validated);
            return response()->json($data);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
