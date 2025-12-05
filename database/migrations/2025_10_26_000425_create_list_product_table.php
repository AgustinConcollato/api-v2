<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('list_product', function (Blueprint $table) {

            // Relación con PRICE_LISTS (ID autoincremental)
            $table->foreignId('price_list_id')->constrained()->onDelete('cascade');

            // Relación con PRODUCTS (UUID)
            // Se usa 'uuid' y se hace referencia explícita a la columna 'id' de la tabla 'products'.
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // --- El campo crucial para el precio diferenciado ---
            // Asumo formato de 8 dígitos en total, 2 decimales (ej. 999999.99)
            $table->decimal('price', 8, 2);

            // Definir la clave primaria compuesta para asegurar unicidad
            $table->primary(['price_list_id', 'product_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_product');
    }
};
