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
        Schema::create('supplier_purchase_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_purchase_id');
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('payment_method', ['cash', 'transfer', 'credit_card', 'check'])->nullable();
            $table->string('note')->nullable();

            $table->timestamps();

            $table->foreign('supplier_purchase_id')->references('id')->on('supplier_purchases')->onDelete('cascade');

            $table->index('supplier_purchase_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_payments');
    }
};
