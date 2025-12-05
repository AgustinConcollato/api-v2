<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Necesario para la PK UUID
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use HasFactory, HasUuids; // Usar HasUuids para PK 'id' de tipo UUID

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'contact_person',
        'address',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Relación muchos a muchos con Products.
     * Un proveedor suministra muchos productos, con datos extra en la tabla pivote.
     */
    public function products(): BelongsToMany
    {
        // Usamos ->withPivot() para acceder a los campos adicionales en la tabla 'product_supplier'
        return $this->belongsToMany(Product::class, 'product_supplier', 'supplier_id', 'product_id')
            ->withPivot('purchase_price', 'supplier_product_url')
            ->withTimestamps();
    }
}
