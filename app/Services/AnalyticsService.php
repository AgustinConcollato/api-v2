<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Obtener métricas agregadas de pedidos según filtros opcionales.
     * Filtros: start_date, end_date, status, client_id, range
     * @param array $filters
     * @return array
     */
    public function getOverview(array $filters = []): array
    {
        $query = Order::query();

        // EXCLUIR cancelled y pending por defecto
        // $query->whereNotIn('status', ['cancelled', 'pending']);
        $query->where('status', '=', 'delivered');

        $isMonthRange = false;

        // Aplicar filtros de fecha
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [
                $filters['start_date'] . ' 00:00:00',
                $filters['end_date'] . ' 23:59:59',
            ]);
        } elseif (!empty($filters['range'])) {
            // Rango rápido
            if ($filters['range'] === 'week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($filters['range'] === 'month') {
                $isMonthRange = true;
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            } elseif ($filters['range'] === 'all') {
                // no filter
            } elseif ($filters['range'] === 'custom') {
                // custom pero sin fechas -> no filtrar (fallback al mes actual)
                if (empty($filters['start_date']) || empty($filters['end_date'])) {
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                }
            }
        } else {
            // Si no hay fechas explícitas ni rango, usar el mes actual por defecto
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

        // Total pagado (pagos completados)
        $totalPaid = (float) DB::table('payments')
            ->whereIn('order_id', $orderIds)
            ->where('status', 'completed')
            ->sum('amount');

        $remainingToCollect = max(0, $totalRevenue - $totalPaid);

        // Costo total (suma de purchase_price * 1.05 * quantity)
        $totalCost = (float) DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->select(DB::raw('SUM((purchase_price * 1.05) * quantity) as total'))
            ->value('total') ?? 0;

        $profit = $totalRevenue - $totalCost;
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

        // Calcular reinversión (10% de ganancia neta)
        $reinvestmentAmount = $profit * 0.1;

        $result = [
            'orders_count' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_debt' => round($remainingToCollect, 2),
            'total_cost' => round($totalCost, 2),
            'gross_profit' => round($profit, 2),
            'net_profit' => round($profit, 2),
            'reinvestment_amount' => round($reinvestmentAmount, 2),
            'gross_margin_percent' => round($grossMarginPercent, 2),
            'average_order_value' => round($averageOrderValue, 2),
            'top_products' => $topProducts,
            'payments_by_status' => $paymentsByStatus,
        ];

        // Si el rango es "month", calcular automáticamente comparación con mes anterior
        if ($isMonthRange) {
            $prev = now()->copy()->subMonth();
            $startPrev = $prev->copy()->startOfMonth()->toDateString();
            $endPrev = $prev->copy()->endOfMonth()->toDateString();
            $overviewPreviousMonth = $this->getOverview(['start_date' => $startPrev, 'end_date' => $endPrev]);

            $result['comparison'] = $this->computeComparison($result, $overviewPreviousMonth);
            $result['previous_month'] = $overviewPreviousMonth;
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

        $overviewA = $this->getOverview(['start_date' => $startA, 'end_date' => $endA]);
        $overviewB = $this->getOverview(['start_date' => $startB, 'end_date' => $endB]);

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
