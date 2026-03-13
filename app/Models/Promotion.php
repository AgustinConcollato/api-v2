<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'starts_at',
        'ends_at',
        'is_active',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_quantity',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'min_quantity' => 'integer',
    ];

    /**
     * Promoción aplicada sobre muchos productos.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    /**
     * Listas de precio a las que aplica la promoción.
     * Si no tiene ninguna asociada, se asume que aplica a todas.
     */
    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class, 'promotion_price_list');
    }

    /**
     * Scope para obtener solo promociones activas y dentro de fecha.
     */
    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }
}

