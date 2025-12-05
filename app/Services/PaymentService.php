<?php
// app/Services/PaymentService.php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

class PaymentService
{
    /**
     * Procesa y registra un nuevo pago para un pedido.
     * @param Order $order El pedido al que se aplica el pago.
     * @param array $data Los datos del pago (monto, método, etc.).
     * @return Payment
     */
    public function processPayment(Order $order, array $data): Payment
    {
        $orderService = new OrderService();
        $pendingBalance = $orderService->getPendingBalance($order);
        $amountPaid = $data['amount'];

        if ($amountPaid <= 0) {
            throw new \Exception("El monto del pago debe ser positivo.");
        }

        // Opcional: Impedir sobrepago (si es estricto)
        // if ($amountPaid > $pendingBalance) {
        //     throw new Exception("El monto pagado excede el saldo pendiente. Saldo pendiente: " . $pendingBalance);
        // ver si guardar lo que pago de más en algun lado
        // }

        // 2. CREACIÓN DEL PAGO
        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'payment_method' => $data['payment_method'],
            'amount' => $amountPaid,
            'status' => $data['status'] ?? PaymentStatus::Completed,
            'payment_date' => now(),
        ]);

        if ($orderService->getPendingBalance($order) <= 0 && $order->status == 'pending') {
            $order->update(['status' => OrderStatus::Confirmed]);
        }

        return $payment;
    }

    /**
     * Registra un reembolso.
     * @param Order $order
     * @param float $amount
     * @return Payment
     */
    public function refund(Order $order, array $data): Payment
    {
        if ($data['amount'] <= 0) {
            throw new \Exception("El monto del reembolso debe ser positivo.");
        }

        return Payment::create([
            'order_id' => $order->id,
            'payment_method' => $data['payment_method'],
            'amount_paid' => $data['amount'], // Usar monto negativo para indicar salida de dinero
            'status' => PaymentStatus::Refunded,
            'payment_date' => now(),
        ]);
    }
}
