<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Relación uno a muchos recursiva (padre).
     * Una categoría pertenece a una categoría padre (Parent Category).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id')->with('parent');
    }

    /**
     * Relación uno a muchos recursiva (hijos).
     * Una categoría tiene muchas subcategorías (Child Categories).
     */
    public function children(): HasMany
    {
        // Cargar hijos y también el padre de cada hijo para que el front reciba la jerarquía completa.
        return $this->hasMany(Category::class, 'parent_id')->with(['children', 'parent']);
    }

    /**
     * Relación muchos a muchos con Products.
     * Una categoría tiene muchos productos.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public static function getTopLevelCategories()
    {
        $categories = Category::whereNull('parent_id')
            ->with(['children.parent', 'children.children'])
            ->orderBy('name')
            ->get();

        return $categories;
    }

    /**
     * Generar el slug automáticamente al guardar
     */

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->slug = $model->slug ?? Str::slug($model->name);
        });
    }
}
