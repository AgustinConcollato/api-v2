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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->unsignedInteger('quantity');

            // Campos de Precio y Descuentos a nivel de línea
            $table->decimal('unit_price', 8, 2);
            $table->decimal('purchase_price', 8, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('discount_fixed_amount', 10, 2)->default(0.00);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('subtotal_with_discount', 10, 2);

            $table->timestamps();

            // Claves Foráneas
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
