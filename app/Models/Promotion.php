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
        'show_on_web',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_quantity',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_on_web' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'min_quantity' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot(['discount_type', 'discount_value', 'max_discount_amount', 'min_quantity'])
            ->withTimestamps();
    }

    /**
     * Resuelve las condiciones efectivas para un producto, aplicando sus overrides del pivot
     * si los tiene, o heredando las de la promo (patrón plan-suscriptor).
     */
    public function getEffectiveConditions(?object $pivot = null): array
    {
        return [
            'discount_type'       => $pivot?->discount_type       ?? $this->discount_type,
            'discount_value'      => $pivot?->discount_value      ?? $this->discount_value,
            'max_discount_amount' => $pivot?->max_discount_amount ?? $this->max_discount_amount,
            'min_quantity'        => $pivot?->min_quantity        ?? $this->min_quantity,
        ];
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

