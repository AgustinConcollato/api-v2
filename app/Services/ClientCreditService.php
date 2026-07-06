<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\ClientCredit;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ClientCreditService
{
    // Estados de pedido "finalizados" elegibles para recibir crédito automático.
    private const SETTLEABLE_STATUSES = ['confirmed', 'shipped', 'delivered'];

    /**
     * Saldo a favor actual del cliente (suma de movimientos, nunca negativo por diseño).
     */
    public function getBalance(Client $client): float
    {
        return round((float) $client->creditMovements()->sum('amount'), 2);
    }

    /**
     * Registra un pago a nivel cliente: agrega crédito y lo reparte automáticamente
     * entre los pedidos finalizados con deuda (FIFO). Lo que sobra queda a favor.
     *
     * @return array{applied: array, credited_remaining: float, credit_balance: float}
     */
    public function deposit(Client $client, float $amount, string $method, ?string $note = null): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto debe ser positivo.');
        }

        return DB::transaction(function () use ($client, $amount, $method, $note) {
            // Lock de los movimientos del cliente para no aplicar el mismo crédito dos veces.
            $client->creditMovements()->lockForUpdate()->get();

            ClientCredit::create([
                'client_id'      => $client->id,
                'amount'         => $amount,
                'payment_method' => $method,
                'note'           => $note,
            ]);

            $applied = $this->settle($client);

            $balance = $this->getBalance($client);

            return [
                'applied'            => $applied,
                'credited_remaining' => $balance,
                'credit_balance'     => $balance,
            ];
        });
    }

    /**
     * Aplica el crédito del cliente a sus pedidos con deuda dentro de una transacción
     * con lock. Usado al confirmar un pedido (dispara el reparto del saldo a favor).
     *
     * @return array<int, array{order_id: string, number: int, amount: float, payment_method: ?string}>
     */
    public function settleClient(Client $client): array
    {
        return DB::transaction(function () use ($client) {
            $client->creditMovements()->lockForUpdate()->get();
            return $this->settle($client);
        });
    }

    /**
     * Aplica el crédito disponible del cliente a sus pedidos finalizados con deuda,
     * del más viejo al más nuevo (FIFO). Por cada tramo aplicado crea un Payment en
     * el pedido y un movimiento negativo de crédito. Devuelve las aplicaciones.
     *
     * Debe ejecutarse dentro de una transacción (deposit y el hook de confirmación
     * ya la abren).
     *
     * @return array<int, array{order_id: string, number: int, amount: float, payment_method: ?string}>
     */
    public function settle(Client $client): array
    {
        $lots = $this->availableLots($client); // cola FIFO de ['method' => ?, 'remaining' => float]
        if (empty($lots)) {
            return [];
        }

        $orders = $client->orders()
            ->whereIn('status', self::SETTLEABLE_STATUSES)
            ->orderBy('created_at')
            ->get();

        $applied  = [];
        $lotIndex = 0;

        foreach ($orders as $order) {
            if ($lotIndex >= count($lots)) {
                break; // sin crédito disponible
            }

            $pending = $this->pendingBalance($order);
            if ($pending <= 0.005) {
                continue;
            }

            while ($pending > 0.005 && $lotIndex < count($lots)) {
                if ($lots[$lotIndex]['remaining'] <= 0.005) {
                    $lotIndex++;
                    continue;
                }

                $method = $lots[$lotIndex]['method'];
                $take   = round(min($pending, $lots[$lotIndex]['remaining']), 2);
                if ($take <= 0) {
                    break;
                }

                $payment = Payment::create([
                    'order_id'       => $order->id,
                    'payment_method' => $method,
                    'amount'         => $take,
                    'status'         => PaymentStatus::Completed,
                    'payment_date'   => now(),
                ]);

                ClientCredit::create([
                    'client_id'      => $client->id,
                    'amount'         => -$take,
                    'payment_method' => $method,
                    'order_id'       => $order->id,
                    'payment_id'     => $payment->id,
                    'note'           => "Aplicado al pedido #{$order->number}",
                ]);

                $lots[$lotIndex]['remaining'] = round($lots[$lotIndex]['remaining'] - $take, 2);
                $pending = round($pending - $take, 2);

                $applied[] = [
                    'order_id'       => $order->id,
                    'number'         => $order->number,
                    'amount'         => $take,
                    'payment_method' => $method,
                ];
            }
        }

        return $applied;
    }

    /**
     * Reconstruye la cola FIFO de lotes de crédito disponibles a partir de los
     * movimientos: los positivos agregan lotes (con su método), los negativos los
     * consumen desde el frente. Así el método de cada lote se preserva para atribuir
     * bien el payment_method al aplicarlo.
     *
     * @return array<int, array{method: ?string, remaining: float}>
     */
    private function availableLots(Client $client): array
    {
        $movements = $client->creditMovements()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $queue = [];
        foreach ($movements as $m) {
            $amount = round((float) $m->amount, 2);
            if ($amount > 0) {
                $queue[] = ['method' => $m->payment_method, 'remaining' => $amount];
            } elseif ($amount < 0) {
                $consume = -$amount;
                foreach ($queue as $i => $lot) {
                    if ($consume <= 0.005) {
                        break;
                    }
                    if ($lot['remaining'] <= 0) {
                        continue;
                    }
                    $take = min($lot['remaining'], $consume);
                    $queue[$i]['remaining'] = round($lot['remaining'] - $take, 2);
                    $consume = round($consume - $take, 2);
                }
            }
        }

        return array_values(array_filter($queue, fn($l) => $l['remaining'] > 0.005));
    }

    /**
     * Saldo pendiente de un pedido = max(0, total - pagos completados).
     * (Inline para no depender de OrderService y evitar dependencia circular.)
     */
    private function pendingBalance(Order $order): float
    {
        $paid = (float) $order->payments()
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');

        return round(max(0, (float) $order->final_total_amount - $paid), 2);
    }
}
