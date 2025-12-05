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
        Schema::create('category_product', function (Blueprint $table) {
            // Relación con CATEGORIES
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // CATEGORIE_ID

            // Relación con PRODUCTS (debe coincidir con el tipo de la PK de products, que es UUID)
            // Asumo que la tabla products usa UUID como PK, si usa ID, usa foreignId().
            $table->uuid('product_id'); 
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade'); // PRODUCT_ID

            // Definir la clave primaria compuesta
            $table->primary(['category_id', 'product_id']); 
            
            // Si necesitas timestamps en la tabla pivote: $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};