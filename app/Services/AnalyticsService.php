<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Obtener métricas agregadas de pedidos según filtros opcionales.
     * Filtros: start_date, end_date, status, client_id, range
     * @param array $filters
     * @return array
     */
    public function getOverview(array $filters = [], bool $withComparison = true): array
    {
        $query = Order::query();

        // EXCLUIR cancelled y pending por defecto
        // $query->whereNotIn('status', ['cancelled', 'pending']);
        $query->where('status', '=', 'delivered');

        $effectiveStart = null;
        $effectiveEnd   = null;

        // Aplicar filtros de fecha y registrar el rango efectivo para comparación
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $effectiveStart = $filters['start_date'];
            $effectiveEnd   = $filters['end_date'];
            $query->whereBetween('created_at', [
                $effectiveStart . ' 00:00:00',
                $effectiveEnd   . ' 23:59:59',
            ]);
        } elseif (!empty($filters['range'])) {
            if ($filters['range'] === 'week') {
                $effectiveStart = now()->startOfWeek()->toDateString();
                $effectiveEnd   = now()->endOfWeek()->toDateString();
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($filters['range'] === 'month') {
                $effectiveStart = now()->startOfMonth()->toDateString();
                $effectiveEnd   = now()->endOfMonth()->toDateString();
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            } elseif ($filters['range'] === 'all') {
                // sin límite — no hay comparación posible
            }
        } else {
            $effectiveStart = now()->startOfMonth()->toDateString();
            $effectiveEnd   = now()->endOfMonth()->toDateString();
            $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        $orders = $query->with(['details', 'details.product.categories'])->get();

        $orderIds = $orders->pluck('id')->all();

        $totalOrders = $orders->count();
        $totalRevenue = (float) $orders->sum('final_total_amount');
        $shippingCost = (float) $orders->sum('shipping_cost');

        // Total pagado (pagos completados) de los pedidos del período
        $totalPaid = (float) DB::table('payments')
            ->whereIn('order_id', $orderIds)
            ->where('status', 'completed')
            ->sum('amount');

        $remainingToCollect = max(0, $totalRevenue - $totalPaid);

        // Efectivo real recibido en el período (sin filtro de pedido)
        if ($effectiveStart && $effectiveEnd) {
            $totalCollectedInPeriod = (float) DB::table('payments')
                ->where('status', 'completed')
                ->whereBetween('created_at', [
                    $effectiveStart . ' 00:00:00',
                    $effectiveEnd   . ' 23:59:59',
                ])
                ->sum('amount');
        } else {
            $totalCollectedInPeriod = (float) DB::table('payments')
                ->where('status', 'completed')
                ->sum('amount');
        }

        // Costo total: (purchase_price + freight_per_unit) * quantity
        $totalCost = (float) DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->select(DB::raw('SUM((purchase_price + freight_per_unit) * quantity) as total'))
            ->value('total') ?? 0;

        $profit = $totalRevenue - $totalCost - $shippingCost;
        $grossMarginPercent = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;

        $averageOrderValue = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;

        // Top productos por cantidad y por ingresos (con nombre y categoría)
        $topProducts = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->select(
                'order_details.product_id',
                'products.name as product_name',
                DB::raw('SUM(order_details.quantity) as total_quantity'),
                DB::raw('SUM(order_details.subtotal_with_discount) as total_revenue')
            )
            ->whereIn('order_details.order_id', $orderIds)
            ->groupBy('order_details.product_id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                // Obtener categorías del producto
                $categories = DB::table('category_product')
                    ->join('categories', 'category_product.category_id', '=', 'categories.id')
                    ->where('category_product.product_id', $product->product_id)
                    ->pluck('categories.name')
                    ->toArray();

                $product->categories = $categories;

                // Intentar obtener la imagen principal desde la tabla images (modelo Image)
                $image = Image::where('product_id', $product->product_id)->orderBy('position', 'asc')->first();
                if ($image && $image->thumbnail_path) {
                    // Generar URL pública si el archivo está en storage
                    try {
                        $product->image_url = $image->thumbnail_path;
                    } catch (\Throwable $e) {
                        // Fallback a thumbnail_path crudo si Storage falla
                        $product->image_url = $image->thumbnail_path;
                    }
                } else {
                    $product->image_url = null;
                }

                return $product;
            });

        // Desglose de pagos por estado
        $paymentsByStatus = DB::table('payments')
            ->select('status', DB::raw('SUM(amount) as total'))
            ->whereIn('order_id', $orderIds)
            ->groupBy('status')
            ->get();

        // Reinversión: 10% de la ganancia antes de reinversión ($profit = revenue - cost - shipping).
        $reinvestmentAmount = $profit * 0.1;

        // Serie temporal: pagos completados por día del período (no filtrado por pedido)
        if ($effectiveStart && $effectiveEnd) {
            $revenueOverTime = DB::table('payments')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(amount) as revenue'),
                    DB::raw('COUNT(DISTINCT order_id) as orders_count')
                )
                ->where('status', 'completed')
                ->whereBetween('created_at', [
                    $effectiveStart . ' 00:00:00',
                    $effectiveEnd   . ' 23:59:59',
                ])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();
        } else {
            $revenueOverTime = DB::table('payments')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(amount) as revenue'),
                    DB::raw('COUNT(DISTINCT order_id) as orders_count')
                )
                ->whereIn('order_id', $orderIds)
                ->where('status', 'completed')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();
        }

        // Serie temporal: pedidos facturados por día
        $ordersOverTime = DB::table('orders')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(final_total_amount) as billed'),
                DB::raw('COUNT(*) as count')
            )
            ->whereIn('id', $orderIds)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $result = [
            'orders_count' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_debt' => round($remainingToCollect, 2),
            'total_cost' => round($totalCost, 2),
            'net_profit' => round($profit - $reinvestmentAmount, 2),
            'reinvestment_amount' => round($reinvestmentAmount, 2),
            'gross_margin_percent' => round($grossMarginPercent, 2),
            'average_order_value' => round($averageOrderValue, 2),
            'top_products' => $topProducts,
            'payments_by_status' => $paymentsByStatus,
            'shipping_cost' => round($shippingCost, 2),
            'revenue_over_time' => $revenueOverTime,
            'orders_over_time' => $ordersOverTime,
            'total_collected_in_period' => round($totalCollectedInPeriod, 2),
        ];

        // Comparación: solo cuando range=month (mes actual vs mes anterior completo)
        if ($withComparison && !empty($filters['range']) && $filters['range'] === 'month') {
            $prev      = Carbon::now()->copy()->subMonth();
            $prevStart = $prev->copy()->startOfMonth()->toDateString();
            $prevEnd   = $prev->copy()->endOfMonth()->toDateString();

            $overviewPrev = $this->getOverview([
                'start_date' => $prevStart,
                'end_date'   => $prevEnd,
                'client_id'  => $filters['client_id'] ?? null,
            ], false);

            $result['comparison']       = $this->computeComparison($result, $overviewPrev);
            $result['comparison_label'] = 'mes anterior';
        }

        return $result;
    }

    /**
     * Helper privado: computar cambios entre dos overviews.
     */
    private function computeComparison(array $current, array $previous): array
    {
        $metrics = [
            'total_revenue',
            'net_profit',
            'orders_count'
        ];

        $comparison = [];
        foreach ($metrics as $m) {
            $a = isset($current[$m]) ? (float) $current[$m] : 0.0;
            $b = isset($previous[$m]) ? (float) $previous[$m] : 0.0;
            $diff = $a - $b;
            $percent = null;
            if ($b != 0) {
                $percent = ($diff / abs($b)) * 100;
            }
            $comparison[$m] = [
                'current' => round($a, 2),
                'previous' => round($b, 2),
                'diff' => round($diff, 2),
                'percent_change' => is_null($percent) ? null : round($percent, 2),
            ];
        }
        return $comparison;
    }

    /**
     * Comparar estadísticas entre dos meses.
     * Parámetros opcionales en $params: 'month_a','year_a','month_b','year_b'
     * Si no se proveen, compara mes actual (a) vs mes anterior (b).
     * Retorna overview para ambos meses, top_products y un objeto 'comparison' con diffs y %.
     *
     * @param array $params
     * @return array
     */
    public function compareMonths(array $params = []): array
    {
        $now = Carbon::now();

        // Mes A = por defecto mes actual
        $monthA = isset($params['month_a']) ? (int) $params['month_a'] : $now->month;
        $yearA = isset($params['year_a']) ? (int) $params['year_a'] : $now->year;

        // Mes B = por defecto mes anterior
        if (isset($params['month_b']) && isset($params['year_b'])) {
            $monthB = (int) $params['month_b'];
            $yearB = (int) $params['year_b'];
        } else {
            $prev = $now->copy()->subMonth();
            $monthB = $prev->month;
            $yearB = $prev->year;
        }

        $startA = Carbon::create($yearA, $monthA, 1)->startOfMonth()->toDateString();
        $endA = Carbon::create($yearA, $monthA, 1)->endOfMonth()->toDateString();

        $startB = Carbon::create($yearB, $monthB, 1)->startOfMonth()->toDateString();
        $endB = Carbon::create($yearB, $monthB, 1)->endOfMonth()->toDateString();

        $overviewA = $this->getOverview(['start_date' => $startA, 'end_date' => $endA], false);
        $overviewB = $this->getOverview(['start_date' => $startB, 'end_date' => $endB], false);

        // Métricas a comparar
        $metrics = [
            'total_revenue',            
            'net_profit',
            'orders_count'
        ];

        $comparison = [];
        foreach ($metrics as $m) {
            $a = isset($overviewA[$m]) ? (float) $overviewA[$m] : 0.0;
            $b = isset($overviewB[$m]) ? (float) $overviewB[$m] : 0.0;
            $diff = $a - $b;
            $percent = null;
            if ($b != 0) {
                $percent = ($diff / abs($b)) * 100;
            }
            $comparison[$m] = [
                'a' => round($a, 2),
                'b' => round($b, 2),
                'diff' => round($diff, 2),
                'percent_change' => is_null($percent) ? null : round($percent, 2),
            ];
        }

        return [
            'month_a' => ['month' => $monthA, 'year' => $yearA, 'start_date' => $startA, 'end_date' => $endA, 'overview' => $overviewA],
            'month_b' => ['month' => $monthB, 'year' => $yearB, 'start_date' => $startB, 'end_date' => $endB, 'overview' => $overviewB],
            'comparison' => $comparison,
            'top_products' => ['a' => $overviewA['top_products'] ?? [], 'b' => $overviewB['top_products'] ?? []],
        ];
    }
}
