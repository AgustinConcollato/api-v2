<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController
{
    public function __construct(
        private PaymentService $paymentService,
        private OrderService $orderService,
    ) {}

    public function index(Request $request)
    {
        $payments = $this->paymentService->getPayments($request->all());
        return response()->json($payments);
    }

    public function store(StorePaymentRequest $request)
    {
        $validated = $request->validated();

        $order = Order::find($validated['order_id']);
        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $payment = $this->paymentService->processPayment($order, $validated);

        $order->refresh();
        $order->load('payments', 'client', 'details');
        $order->balance_due = $this->orderService->getPendingBalance($order);

        return response()->json([
            'payment' => new PaymentResource($payment),
            'order'   => new OrderResource($order),
        ], 201);
    }

    public function paymentsByOrder(Order $order)
    {
        $payments = $order->payments()->orderBy('payment_date', 'desc')->get();

        return PaymentResource::collection($payments);
    }

    public function storeRefund(StoreRefundRequest $request)
    {
        $validated = $request->validated();

        $order = Order::find($validated['order_id']);

        $refundPayment = $this->paymentService->refund($order, $validated);

        return response()->json(new PaymentResource($refundPayment), 201);
    }
}
