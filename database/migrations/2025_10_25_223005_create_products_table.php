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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID (como primary key, según tu boceto)
            $table->string('name'); // NAME
            $table->text('description')->nullable(); // DESCRIPTION
            $table->unsignedInteger('stock')->default(0); // STOCK
            $table->string('sku')->unique()->nullable(); // SKU
            $table->decimal('price', 10, 2); // PRICE
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};