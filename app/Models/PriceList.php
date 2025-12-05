<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Relación muchos a muchos con Products.
     * Una lista de precios contiene muchos productos con su precio asociado.
     */
    public function products(): BelongsToMany
    {
        // La tabla pivote es 'list_product'.
        // Usamos withPivot('price') para acceder al precio específico.
        return $this->belongsToMany(Product::class, 'list_product', 'price_list_id', 'product_id')
            ->withPivot('price')
            ->withTimestamps();
    }

    /**
     * Relación uno a muchos con Clients.
     * Una lista de precios puede estar asignada a muchos clientes.
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
