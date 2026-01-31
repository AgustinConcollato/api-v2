<?php

namespace App\Models;

use App\Enums\OrderStatus;
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

    protected $casts = [
        'status' => OrderStatus::class, // <-- Esto es vital
    ];

    protected static array $statusTransitions = [
        OrderStatus::Pending->value    => [OrderStatus::Processing, OrderStatus::Cancelled],
        OrderStatus::Processing->value => [OrderStatus::Confirmed, OrderStatus::Cancelled],
        OrderStatus::Confirmed->value  => [OrderStatus::Shipped, OrderStatus::Cancelled],
        OrderStatus::Shipped->value    => [OrderStatus::Delivered, OrderStatus::Cancelled],
        OrderStatus::Delivered->value  => [],
        OrderStatus::Cancelled->value  => [],
    ];

    /**
     * Verifica si el pedido puede cambiar a un nuevo estado.
     */
    public function canTransitionTo(OrderStatus|string $newStatus): bool
    {
        // 1. Si recibimos un string, lo intentamos convertir a Enum
        if (is_string($newStatus)) {
            $newStatus = OrderStatus::tryFrom($newStatus);
        }
    
        // 2. Si el nuevo estado no es válido o no existe en el Enum
        if (!$newStatus) return false;
    
        // 3. Obtenemos el valor actual (manejando si por alguna razón es string)
        $currentStatusValue = $this->status instanceof OrderStatus 
            ? $this->status->value 
            : $this->status;
    
        // 4. Si el estado es el mismo, no hay transición
        if ($currentStatusValue === $newStatus->value) return true;
    
        // 5. Validamos contra el mapa de transiciones
        $allowed = self::$statusTransitions[$currentStatusValue] ?? [];
        
        return in_array($newStatus, $allowed);
    }

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

    /**
     * Costo del pedido
     */
    public function getTotalCostAttribute()
    {
        // Suma el precio de compra * cantidad de cada detalle
        return $this->details->sum(function ($detail) {
            return ($detail->purchase_price * 1.05) * $detail->quantity;
        });
    }
}
