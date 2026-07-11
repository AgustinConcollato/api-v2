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
        Schema::create('supplier_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->string('invoice_number')->nullable();
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->decimal('total', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            // Calculado en el service: total - total * discount_percent / 100.
            $table->decimal('total_with_discount', 12, 2);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');

            $table->index('supplier_id');
            $table->index('purchase_date');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_purchases');
    }
};
