<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Para usar UUIDs

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'status',
        'payment_date',
    ];

    /**
     * Indica que 'payment_date' es un campo de fecha para que Laravel lo gestione como un objeto Carbon.
     */
    protected $casts = [
        'payment_date' => 'datetime',
    ];

    /**
     * El pago pertenece a un Pedido.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
