<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Necesario para la PK UUID
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class Product extends Model
{
    use HasFactory, HasUuids; // Usar HasUuids para PK 'id' de tipo UUID

    // Indicar a Eloquent que la clave primaria es 'id' (UUID) y no es autoincremental (por si acaso).
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'stock',
        'sku',
        'status'
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'stock' => 'integer',
    ];

    /**
     * Relación uno a muchos con Images.
     * Un producto tiene muchas imágenes.
     */
    public function images(): HasMany
    {
        // La clave foránea en la tabla 'images' es 'product_id'
        return $this->hasMany(Image::class, 'product_id');
    }

    /**
     * Relación muchos a muchos con Categories.
     * Un producto pertenece a muchas categorías.
     */
    public function categories(): BelongsToMany
    {
        // La tabla pivote por convención sería 'category_product'
        return $this->belongsToMany(Category::class);
    }

    /**
     * Relación muchos a muchos con Suppliers.
     * Un producto es suministrado por muchos proveedores, con datos extra en la tabla pivote.
     */
    public function suppliers(): BelongsToMany
    {
        // Usamos ->withPivot() para acceder a los campos adicionales en la tabla 'product_supplier'
        return $this->belongsToMany(Supplier::class, 'product_supplier', 'product_id', 'supplier_id')
            ->withPivot('purchase_price', 'supplier_product_url')
            ->withTimestamps();
    }

    /**
     * El producto pertenece a muchas listas de precios.
     */
    public function priceLists(): BelongsToMany
    {
        // En tu DB, la tabla pivot es 'list_product'.
        // Los IDs son 'price_list_id' y 'product_id'.
        // El campo adicional en la tabla pivot es 'price'.
        return $this->belongsToMany(PriceList::class, 'list_product', 'product_id', 'price_list_id')
            ->withPivot('price')
            ->withTimestamps(); // Si quieres que created_at/updated_at se actualicen
    }

    /**
     * Summary of getPriceByListId
     * @param int $priceListId
     * @return float|null
     */
    public function getPriceByListId(int $priceListId): ?float
    {
        // 1. Usar la relación priceLists()
        // 2. Filtrar la relación por el price_list_id deseado
        // 3. Seleccionar solo la columna 'price' de la tabla pivote
        // 4. Usar ->first() para obtener el primer resultado

        $priceData = $this->priceLists()
            ->where('price_list_id', $priceListId)
            ->select('price') // Selecciona el precio de la tabla 'list_product'
            ->first();

        // Si se encuentra el registro, devuelve el valor del campo 'price' del pivot.
        if ($priceData) {
            // El campo 'price' se encuentra dentro de la propiedad 'pivot'
            return (float) $priceData->pivot->price;
        }

        // Si no se encuentra, devuelve null
        return null;
    }


    /**
     * Define la relación con los códigos de barras.
     * Un Producto tiene muchos ProductBarcodes.
     */
    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * Busca un producto por un código de barras escaneado.
     */
    public static function findByBarcode(string $scannedCode): ?self
    {
        $barcodeEntry = ProductBarcode::where('barcode', $scannedCode)->first();

        return $barcodeEntry ? $barcodeEntry->product : null;
    }

    /**
     * Accesor para obtener el código de barras primario (opcional).
     * Útil para mostrarlo por defecto en vistas o documentos.
     */
    public function getPrimaryBarcodeAttribute()
    {
        // Busca el primer código marcado como primario, o el primero que encuentre.
        return $this->barcodes()->where('is_primary', true)->first()
            ?? $this->barcodes()->first();
    }


    // Sirve para ver en que pedidos fue vendido en producto
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }
}
