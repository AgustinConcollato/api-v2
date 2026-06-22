<?php
// app/Services/PaymentService.php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

class PaymentService
{
    public function __construct(private OrderService $orderService) {}

    /**
     * Procesa y registra un nuevo pago para un pedido.
     * @param Order $order El pedido al que se aplica el pago.
     * @param array $data Los datos del pago (monto, método, etc.).
     * @return Payment
     */
    public function processPayment(Order $order, array $data): Payment
    {
        $pendingBalance = $this->orderService->getPendingBalance($order);
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

        if ($this->orderService->getPendingBalance($order) <= 0 && $order->status == 'pending') {
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

    /**
     * Muestra una lista de todos los pagos.
     * @param array $filters Array con los filtros de búsqueda
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPayments(array $filters)
    {

        $query = Payment::with('order.client')->orderBy('payment_date', 'desc');

        // lo que llega en filters es: start_date,end_date,range,status,payment_method,order_id

        // Filtro por Fechas (mismo formato que OrderService: rango manual o rango rápido)
        if (isset($filters['start_date']) && isset($filters['end_date']) && $filters['start_date'] !== '' && $filters['end_date'] !== '') {
            $query->whereBetween('payment_date', [
                $filters['start_date'] . ' 00:00:00',
                $filters['end_date'] . ' 23:59:59'
            ]);
        } elseif (isset($filters['range'])) {
            if ($filters['range'] === 'week') {
                $query->whereBetween('payment_date', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($filters['range'] === 'month') {
                $query->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year);
            }
            // Si $filters['range'] === 'all', no se aplica filtro de fecha (todo el historial).
        }

        // Filtro por Status
        $query->when(isset($filters['status']), function ($q) use ($filters) {
            $q->where('status', $filters['status']);
        });

        // Filtro por Payment Method
        $query->when(isset($filters['payment_method']), function ($q) use ($filters) {
            $q->where('payment_method', $filters['payment_method']);
        });

        // Filtro por número de pedido
        $query->when(isset($filters['order_number']), function ($q) use ($filters) {
            $q->whereHas('order', fn($q2) => $q2->where('number', $filters['order_number']));
        });

        // Filtro por Cliente
        $query->when(isset($filters['client_id']), function ($q) use ($filters) {
            $q->whereHas('order', function ($q) use ($filters) {
                $q->where('client_id', $filters['client_id']);
            });
        });

        $stats = (clone $query)
            ->selectRaw("
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_method = 'transfer' THEN 1 ELSE 0 END) as transfer_count,
                SUM(CASE WHEN payment_method = 'cash' THEN 1 ELSE 0 END) as cash_count,
                SUM(CASE WHEN payment_method = 'check' THEN 1 ELSE 0 END) as check_count,
                SUM(CASE WHEN payment_method = 'credit_card' THEN 1 ELSE 0 END) as credit_card_count
            ")
            ->reorder()
            ->first();

        $paginated = $query->paginate(20);

        return [
            'data'         => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
            'stats'        => [
                'total_amount'     => (float) ($stats->total_amount ?? 0),
                'transfer_count'   => (int) ($stats->transfer_count ?? 0),
                'cash_count'       => (int) ($stats->cash_count ?? 0),
                'check_count'      => (int) ($stats->check_count ?? 0),
                'credit_card_count' => (int) ($stats->credit_card_count ?? 0),
            ],
        ];
    }
}
