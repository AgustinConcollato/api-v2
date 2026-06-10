<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Mail\OrderPendingMail;
use App\Services\WholesaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WholesaleController
{
    public function __construct(private readonly WholesaleService $wholesaleService) {}

    public function checkout(CheckoutRequest $request)
    {
        $data = $request->validated();

        $resolved = $this->wholesaleService->resolveItems($data['items']);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        try {
            $order = $this->wholesaleService->createOrder($data, $resolved);

            try {
                Mail::to(config('mail.order_notify'))
                    ->send(new OrderPendingMail($order->load('client', 'details.product', 'details.variant')));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el aviso de pedido: ' . $e->getMessage());
            }

            return response()->json([
                'order_id' => $order->id,
                'message'  => 'Pedido recibido',
            ], 201);
        } catch (\App\Exceptions\InsufficientStockException $e) {
            return response()->json([
                'message'      => 'Algunos productos se quedaron sin stock mientras finalizabas la compra.',
                'stock_errors' => $e->errors,
            ], 409);
        }
    }
}
