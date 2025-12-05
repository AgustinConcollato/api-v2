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
        Schema::create('images', function (Blueprint $table) {
            $table->id(); // ID
            
            // Relación con PRODUCTS (debe coincidir con el tipo de la PK de products, que es UUID)
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade'); // PRODUCT_ID

            $table->string('path'); // PATH (ruta del archivo)
            $table->string('thumbnail_path'); // PATH (ruta del archivo)
            $table->unsignedSmallInteger('position')->default(0); // POSITION (para ordenar las imágenes)
            $table->timestamps();

            // Agregar un índice para búsquedas por producto y ordenar por posición
            $table->index(['product_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};