<?php

namespace App\Services;

use App\Models\SupplierPurchase;
use App\Models\SupplierPurchasePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class SupplierPurchaseService
{
    /**
     * Columnas ordenables desde el front y su columna real en la tabla.
     */
    private const SORTABLE_COLUMNS = [
        'purchase_date' => 'purchase_date',
        'due_date' => 'due_date',
        'invoice_number' => 'invoice_number',
        'total' => 'total_with_discount',
    ];

    /**
     * Lista paginada de compras con filtros, más estadísticas globales del set filtrado.
     *
     * @param array $filters supplier_id, status, start_date, end_date, invoice_number, page, per_page, sort_by, sort_dir
     * @return array{data: LengthAwarePaginator, stats: array}
     */
    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = SupplierPurchase::query()
            ->with(['supplier', 'payments'])
            ->withSum('payments as paid_sum', 'amount');

        $this->applyBaseFilters($query, $filters);
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        $sortColumn = self::SORTABLE_COLUMNS[$filters['sort_by'] ?? ''] ?? null;
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortColumn) {
            // Vencimientos nulos siempre al final, sin importar la dirección.
            if ($sortColumn === 'due_date') {
                $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $sortDir);
            } else {
                $query->orderBy($sortColumn, $sortDir);
            }
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('purchase_date')->orderByDesc('created_at');
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => $paginator,
            'stats' => $this->buildStats($filters),
        ];
    }

    /**
     * Calcula estadísticas globales (SE DEBE, PAGO TOTAL, vencidas) sobre el set filtrado
     * sin paginación (respeta los mismos filtros base + estado).
     */
    private function buildStats(array $filters): array
    {
        $base = SupplierPurchase::query()->withSum('payments as paid_sum', 'amount');
        $this->applyBaseFilters($base, $filters);
        $this->applyStatusFilter($base, $filters['status'] ?? null);

        $rows = $base->get(['id', 'total', 'total_with_discount', 'due_date']);

        $today = Carbon::today();
        $totalPaid = 0.0;
        $totalDebt = 0.0;
        $overdueAmount = 0.0;
        $overdueCount = 0;

        foreach ($rows as $row) {
            $paid = (float) ($row->paid_sum ?? 0);
            $twd = (float) $row->total_with_discount;

            $overdue = $row->due_date
                && $paid + 0.001 < $twd
                && $row->due_date->lt($today);

            // El descuento es por pronto pago: si venció sin saldarse, se paga el total.
            $payable = ($paid + 0.001 >= $twd) ? $twd : ($overdue ? (float) $row->total : $twd);
            $balance = max($payable - $paid, 0);

            $totalPaid += $paid;
            $totalDebt += $balance;

            if ($overdue && $balance > 0) {
                $overdueAmount += $balance;
                $overdueCount++;
            }
        }

        return [
            'total_debt' => round($totalDebt, 2),
            'total_paid' => round($totalPaid, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_count' => $overdueCount,
            'count' => $rows->count(),
        ];
    }

    private function applyBaseFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['invoice_number'])) {
            $query->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('purchase_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('purchase_date', '<=', $filters['end_date']);
        }
    }

    /**
     * Filtro por estado derivado de la suma de pagos (paid_sum).
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if (!$status) {
            return;
        }

        $paid = 'COALESCE((SELECT SUM(amount) FROM supplier_purchase_payments WHERE supplier_purchase_id = supplier_purchases.id), 0)';

        switch ($status) {
            case 'pending':
                $query->whereRaw("$paid <= 0");
                break;
            case 'partial':
                $query->whereRaw("$paid > 0 AND $paid < total_with_discount");
                break;
            case 'paid':
                $query->whereRaw("$paid >= total_with_discount");
                break;
            case 'overdue':
                $query->whereDate('due_date', '<', Carbon::today())
                    ->whereRaw("$paid < total_with_discount");
                break;
        }
    }

    /**
     * Proveedores que tienen al menos una compra cargada (para el filtro).
     *
     * @return \Illuminate\Support\Collection
     */
    public function suppliersWithPurchases(): \Illuminate\Support\Collection
    {
        $ids = SupplierPurchase::query()->distinct()->pluck('supplier_id');

        return \App\Models\Supplier::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function store(array $data): SupplierPurchase
    {
        $data['total_with_discount'] = $this->computeDiscounted($data);

        $purchase = SupplierPurchase::create($data);

        return $purchase->load(['supplier', 'payments']);
    }

    public function update(SupplierPurchase $purchase, array $data): SupplierPurchase
    {
        $data['total_with_discount'] = $this->computeDiscounted($data);

        // Si cambió el vencimiento, re-armar el recordatorio (que pueda volver a enviarse).
        $newDue = isset($data['due_date']) ? (string) $data['due_date'] : null;
        $oldDue = $purchase->due_date?->toDateString();
        if ($newDue !== $oldDue) {
            $data['reminder_sent_at'] = null;
        }

        $purchase->update($data);

        return $purchase->fresh(['supplier', 'payments']);
    }

    public function destroy(SupplierPurchase $purchase): void
    {
        $purchase->delete();
    }

    /**
     * Registra un pago parcial. Valida que no supere el saldo pendiente.
     */
    public function addPayment(SupplierPurchase $purchase, array $data): SupplierPurchasePayment
    {
        $balance = (float) $purchase->balance;

        if ((float) $data['amount'] > $balance + 0.001) {
            throw new \InvalidArgumentException('El pago no puede superar el saldo pendiente ($' . number_format($balance, 2) . ').');
        }

        $data['supplier_purchase_id'] = $purchase->id;

        return SupplierPurchasePayment::create($data);
    }

    public function deletePayment(SupplierPurchasePayment $payment): void
    {
        $payment->delete();
    }

    /**
     * Facturas que entran en su ventana de aviso (due_date - reminder_days <= hoy <= due_date),
     * con saldo pendiente y sin recordatorio ya enviado.
     *
     * @return Collection<int, SupplierPurchase>
     */
    public function dueSoon(): Collection
    {
        $today = Carbon::today();

        $candidates = SupplierPurchase::query()
            ->with(['supplier', 'payments'])
            ->whereNotNull('due_date')
            ->whereNotNull('reminder_days')
            ->whereNull('reminder_sent_at')
            ->whereDate('due_date', '>=', $today)
            // due_date <= hoy + reminder_days  ⇔  ya estamos dentro de la ventana de aviso.
            ->whereRaw('DATE_SUB(due_date, INTERVAL reminder_days DAY) <= ?', [$today->toDateString()])
            ->orderBy('due_date')
            ->get();

        // Solo las que todavía deben algo.
        return $candidates->filter(fn(SupplierPurchase $p) => (float) $p->balance > 0)->values();
    }

    /**
     * Marca un conjunto de compras como ya avisadas.
     *
     * @param array<int, string> $ids
     */
    public function markReminded(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        SupplierPurchase::whereIn('id', $ids)->update(['reminder_sent_at' => now()]);
    }

    /**
     * total - total * discount_percent / 100.
     */
    private function computeDiscounted(array $data): float
    {
        $total = (float) ($data['total'] ?? 0);
        $percent = (float) ($data['discount_percent'] ?? 0);

        return round($total - ($total * $percent / 100), 2);
    }
}
