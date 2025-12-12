<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'purchase_price',
        'discount_percentage',
        'discount_fixed_amount',
        'subtotal',
        'subtotal_with_discount',
    ];

    /**
     * El detalle de un pedido pertenece a un Pedido.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * El detalle de un pedido corresponde a un Producto.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
