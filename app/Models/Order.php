<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Para usar UUIDs

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_id',
        'status',
        'total_amount',
        'discount_percentage',
        'discount_fixed_amount',
        'shipping_cost',
        'final_total_amount',
        'shipping_address',
        'notes',
        'price_list_id',
    ];

    /**
     * Un Pedido pertenece a un Cliente.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Un Pedido tiene muchos Detalles (productos).
     */
    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * Un Pedido puede tener muchos Pagos (para manejar pagos parciales).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Accesor para obtener el monto total pagado (solo pagos completados).
     * Se accede como $order->paid_amount
     */
    protected function paidAmount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->payments()
                ->where('status', PaymentStatus::Completed)
                ->sum('amount'),
        );
    }

    /**
     * Accesor para obtener el saldo pendiente.
     * Se accede como $order->pending_balance
     */
    protected function pendingBalance(): Attribute
    {
        return Attribute::make(
            get: fn() => max(
                0, // Asegura que no sea negativo si hay sobrepago
                $this->final_total_amount - $this->paid_amount // Usa el accesor anterior
            ),
        );
    }
}
