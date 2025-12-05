<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'barcode',
        'is_primary'
    ];

    /**
     * Define la relación inversa con el Producto.
     * Un ProductBarcode pertenece a un solo Producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
