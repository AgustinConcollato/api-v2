<?php

// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentService;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Registra un nuevo pago para un pedido específico.
     */
    public function store(Request $request)
    {

        $rules = [
            'order_id' => 'required|uuid|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,credit_card,check',
        ];

        $params = [
            'order_id.required' => 'Indica de qué pedido es el pago.',
            'order_id.uuid' => 'El identificador del pedido no tiene un formato válido (UUID).',
            'order_id.exists' => 'El pedido especificado no existe.',

            'amount.required' => 'Debes indicar el monto del pago.',
            'amount.numeric' => 'El monto del pago debe ser un valor numérico.',
            'amount.min' => 'El monto del pago debe ser al menos :min.',

            'payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_method.in' => 'El método de pago seleccionado no es válido.',
        ];

        try {
            // 1. VALIDACIÓN
            $validated =  $request->validate($rules, $params);

            $order = Order::find($validated['order_id']);
            if (!$order) {
                return response()->json(['error' => 'Pedido no encontrado.'], 404);
            }

            // 2. LÓGICA DE NEGOCIO (delegada al Servicio)
            $payment = $this->paymentService->processPayment($order, $request->all());

            // 3. RESPUESTA
            return response()->json($payment, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function paymentsByOrder(Order $order)
    {
        try {
            $payments = $order->payments()->orderBy('payment_date', 'desc')->get();

            return response()->json($payments);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los pagos.'], 500);
        }
    }

    public function storeRefund(Request $request)
    {

        $rules = [
            'order_id' => 'required|uuid|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,transfer,credit_card,check', // Ejemplo de reglas completas
        ];

        $params = [
            'order_id.required' => 'Debes indicar el pedido asociado al pago.',
            'order_id.uuid' => 'El identificador del pedido no tiene un formato válido (UUID).',
            'order_id.exists' => 'El pedido especificado no se encontró en el sistema.',

            'amount.required' => 'Debes especificar el monto del pago.',
            'amount.numeric' => 'El monto del pago debe ser un valor numérico.',
            'amount.min' => 'El monto del pago debe ser al menos :min.',

            'payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_method.in' => 'El método de pago seleccionado no es una opción válida.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $order = Order::find($validated['order_id']);

            $refundPayment = $this->paymentService->refund($order, $validated);

            return response()->json($refundPayment, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
