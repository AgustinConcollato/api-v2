<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class ClientService
{
    /**
     * Summary of create
     * @param array $data
     * @return Client
     */
    public function create(array $data): Client
    {
        $data['password'] = Hash::make($data['password']);
        $client =  Client::create($data);

        return $client->refresh();
    }

    /**
     * Actualiza un cliente existente.
     * @param Client $client La instancia del modelo Client a actualizar.
     * @param array $data Los nuevos datos.
     * @return Client
     */
    public function update(Client $client, array $data): Client
    {
        // Si se proporciona una nueva contraseña, hashearla antes de actualizar
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $client->update($data);

        // Devolver el modelo actualizado, refrescándolo por si hay cambios en la base de datos
        return $client->refresh();
    }

    /**
     * Elimina un cliente existente.
     * @param Client $client La instancia del modelo Client a eliminar.
     * @return bool
     */
    public function delete(Client $client): bool
    {
        // El método delete() devuelve true si la eliminación fue exitosa
        return $client->delete();
    }

    /**
     * Summary of getClients
     * @return \Illuminate\Database\Eloquent\Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return Client::with('priceList')->get();
    }

    /**
     * Arma el detalle del cliente con estadísticas (financieras, comportamiento,
     * top productos e ingresos por mes). Los pedidos cancelados se excluyen de
     * las stats financieras/comportamiento pero siguen contando en total_orders
     * y orders_by_status.
     *
     * @return array
     */
    public function getDetail(Client $client): array
    {
        $client->load([
            'priceList',
            'addresses',
            'orders' => fn($q) => $q->latest(),
            'orders.payments',
            'orders.details.product.images',
            'orders.client',
        ]);

        $client->orders->each(function ($order) {
            $paid = $order->payments
                ->where('status', 'completed')
                ->sum('amount');
            $order->balance_due = max(0, (float) $order->final_total_amount - (float) $paid);
            $order->total_cost  = $order->getTotalCostAttribute();
        });

        // Pedidos válidos: excluyen cancelados (no entran en stats financieras ni de comportamiento)
        $validOrders = $client->orders->reject(fn($o) => $this->statusValue($o) === 'cancelled')->values();
        $validCount  = $validOrders->count();

        $totalSpent  = $validOrders->sum('final_total_amount');
        $totalPaid   = $validOrders->flatMap->payments->where('status', 'completed')->sum('amount');
        $totalCost   = $validOrders->sum('total_cost');
        $ordersCount = $client->orders->count();
        $balanceDue  = $validOrders->sum('balance_due');

        $lastOrderAt   = $validOrders->max('created_at');
        $firstOrderAt  = $validOrders->min('created_at');
        $daysSinceLast = $lastOrderAt
            ? now()->diffInDays(Carbon::parse($lastOrderAt))
            : null;

        $sortedDates    = $validOrders->sortBy('created_at')->pluck('created_at')->values();
        $avgDaysBetween = $sortedDates->count() >= 2
            ? round(
                Carbon::parse($sortedDates->first())
                    ->diffInDays(Carbon::parse($sortedDates->last()))
                    / ($sortedDates->count() - 1)
            )
            : null;

        if ($validCount === 0)                                        $segment = 'sin_pedidos';
        elseif ($validCount === 1)                                    $segment = 'nuevo';
        elseif ($daysSinceLast !== null && $daysSinceLast > 90)       $segment = 'inactivo';
        else                                                          $segment = 'recurrente';

        $stats = [
            'total_orders'     => $ordersCount,
            'total_spent'      => $totalSpent,
            'total_paid'       => $totalPaid,
            'balance_due'      => $balanceDue,
            'cancelled_count'  => $client->orders->filter(fn($o) => $this->statusValue($o) === 'cancelled')->count(),
            'active_count'     => $client->orders->filter(fn($o) => \in_array($this->statusValue($o), ['pending', 'processing', 'confirmed', 'shipped']))->count(),
            'avg_order_value'  => $validCount > 0 ? round($totalSpent / $validCount, 2) : 0,
            'payment_rate'     => $totalSpent > 0 ? round($totalPaid / $totalSpent * 100, 1) : 0,
            'orders_with_debt' => $validOrders->filter(fn($o) => $o->balance_due > 0)->count(),
            'total_cost'       => $totalCost,
            'gross_margin'     => round($totalSpent - $totalCost, 2),
            'gross_margin_pct' => $totalSpent > 0 ? round(($totalSpent - $totalCost) / $totalSpent * 100, 1) : 0,
            'first_order_at'   => $firstOrderAt,
            'last_order_at'    => $lastOrderAt,
            'days_since_last'  => $daysSinceLast * (-1),
            'avg_days_between' => $avgDaysBetween,
            'segment'          => $segment,
            'orders_by_status' => $client->orders->groupBy(fn($o) => $this->statusValue($o))->map->count(),
        ];

        $topProducts = $validOrders
            ->flatMap->details
            ->groupBy('product_id')
            ->map(fn($items) => [
                'product_id'   => $items->first()->product_id,
                'product_name' => $items->first()->product?->name ?? 'Producto eliminado',
                'total_qty'    => $items->sum('quantity'),
                'total_amount' => round($items->sum('subtotal_with_discount'), 2),
                'image'        => $items->first()->product?->images->first()?->thumbnail_path ?? null,
            ])
            ->sortByDesc('total_qty')
            ->take(10)
            ->values();

        $revenueByMonth = $validOrders
            ->groupBy(fn($o) => Carbon::parse($o->created_at)->format('Y-m'))
            ->map(fn($orders, $month) => [
                'month'   => $month,
                'revenue' => round($orders->sum('final_total_amount'), 2),
                'count'   => $orders->count(),
            ])
            ->sortKeys()
            ->values();

        return array_merge($client->toArray(), [
            'stats'            => $stats,
            'top_products'     => $topProducts,
            'revenue_by_month' => $revenueByMonth,
        ]);
    }

    /**
     * Devuelve el valor string del status, sea enum o string.
     */
    private function statusValue(Order $order): ?string
    {
        $status = $order->status;

        return $status instanceof OrderStatus ? $status->value : $status;
    }
}
