<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'stock',
        'stock_updated_at',
        'is_active',
        'is_dropshipping',
    ];

    protected $casts = [
        'stock' => 'integer',
        'stock_updated_at' => 'datetime',
        'is_active' => 'boolean',
        'is_dropshipping' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * La variante pertenece a muchas listas de precios (override propio, opcional).
     */
    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class, 'list_product_variant', 'variant_id', 'price_list_id')
            ->withPivot('price')
            ->withTimestamps();
    }

    /**
     * Nombre a mostrar: el propio si fue overrideado, si no el del producto base.
     */
    public function effectiveName(): string
    {
        return $this->name ?? $this->product->name;
    }

    /**
     * Si esta variante es dropshipping: su propio flag si fue overrideado, si no el del producto base.
     */
    public function isDropshipping(): bool
    {
        return $this->is_dropshipping ?? $this->product->is_dropshipping;
    }

    /**
     * Precio efectivo para una lista de precios: el override propio si existe, si no el del producto base.
     */
    public function effectivePrice(int $priceListId): ?float
    {
        $priceData = $this->priceLists()
            ->where('price_list_id', $priceListId)
            ->select('price')
            ->first();

        if ($priceData) {
            return (float) $priceData->pivot->price;
        }

        return $this->product->getPriceByListId($priceListId);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'variant_id')->with('categoryAttribute.options');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class, 'variant_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'variant_id')->orderBy('position');
    }
}
