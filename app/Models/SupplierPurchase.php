<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchase extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'invoice_number',
        'purchase_date',
        'due_date',
        'total',
        'discount_percent',
        'total_with_discount',
        'note',
        'reminder_days',
        'reminder_sent_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'due_date' => 'date',
        'total' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total_with_discount' => 'decimal:2',
        'reminder_sent_at' => 'datetime',
    ];

    protected $appends = ['amount_paid', 'payable_total', 'balance', 'status', 'is_overdue'];

    /**
     * La compra pertenece a un Proveedor.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Pagos parciales aplicados a esta compra.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPurchasePayment::class);
    }

    /**
     * Total pagado (suma de pagos parciales).
     */
    public function getAmountPaidAttribute(): string
    {
        // Usa la relación ya cargada si existe para evitar consultas extra.
        $sum = $this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * ¿Está vencida y sin saldar? (due_date pasada y no pagó el total con descuento).
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        // Si ya cubrió el total con descuento, la factura está saldada: no vence.
        if ((float) $this->amount_paid + 0.001 >= (float) $this->total_with_discount) {
            return false;
        }

        return $this->due_date->lt(now()->startOfDay());
    }

    /**
     * Monto a pagar. El descuento es por pronto pago: si la factura vence sin
     * saldarse, se pierde y se paga el total sin descuento.
     */
    public function getPayableTotalAttribute(): string
    {
        $paid = (float) $this->amount_paid;
        $twd = (float) $this->total_with_discount;

        // El descuento ya fue honrado (pagó al menos el total con descuento).
        if ($paid + 0.001 >= $twd) {
            return number_format($twd, 2, '.', '');
        }

        // Vencida: se paga el total completo, sin descuento.
        if ($this->is_overdue) {
            return number_format((float) $this->total, 2, '.', '');
        }

        return number_format($twd, 2, '.', '');
    }

    /**
     * Saldo pendiente = monto a pagar - pagado.
     */
    public function getBalanceAttribute(): string
    {
        $balance = (float) $this->payable_total - (float) $this->amount_paid;

        return number_format(max($balance, 0), 2, '.', '');
    }

    /**
     * Estado derivado: pending / partial / paid.
     */
    public function getStatusAttribute(): string
    {
        $paid = (float) $this->amount_paid;
        $payable = (float) $this->payable_total;

        if ($paid <= 0) {
            return 'pending';
        }

        if ($paid + 0.001 >= $payable) {
            return 'paid';
        }

        return 'partial';
    }
}
