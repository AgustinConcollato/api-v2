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
        Schema::create('client_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            // Monto con signo: (+) entra crédito, (-) se consume al aplicarlo a un pedido.
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'credit_card', 'check'])->nullable();
            // Pedido relacionado cuando el movimiento se origina o consume en un pedido.
            $table->uuid('order_id')->nullable();
            // Pago generado al consumir el crédito.
            $table->uuid('payment_id')->nullable();
            $table->string('note')->nullable();

            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');

            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_credits');
    }
};
