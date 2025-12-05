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
        Schema::create('product_supplier', function (Blueprint $table) {

            // Relación con SUPPLIERS (UUID)
            // ⭐ El tipo debe coincidir con suppliers.id
            $table->uuid('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            // Relación con PRODUCTS (UUID)
            // El tipo debe coincidir con products.id
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->decimal('purchase_price', 8, 2);
            $table->string('supplier_product_url', 512)->nullable();

            $table->primary(['supplier_id', 'product_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
