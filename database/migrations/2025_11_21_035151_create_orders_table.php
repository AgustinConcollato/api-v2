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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Usando UUID como en `clients` y `products`
            $table->uuid('client_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled'])->default('pending');

            // Campos de Monto y Descuentos a nivel de pedido
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('discount_fixed_amount', 10, 2)->default(0.00);
            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->decimal('final_total_amount', 10, 2);

            $table->integer('price_list_id')->default(1);
            $table->text('shipping_address')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Clave Foránea
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
