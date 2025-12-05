<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'thumbnail_path',
        'position',
    ];

    /**
     * Relación uno a uno (inversa) con Product.
     * Una imagen pertenece a un producto.
     */
    public function product(): BelongsTo
    {
        // La clave de referencia en la tabla 'products' es de tipo UUID
        // por lo que debemos especificar el tipo de clave foránea.
        return $this->belongsTo(Product::class, 'product_id');
    }
}
