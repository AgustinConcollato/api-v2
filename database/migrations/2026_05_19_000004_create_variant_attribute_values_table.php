<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->onDelete('cascade');
            $table->foreignId('category_attribute_id')
                ->constrained('category_attributes')
                ->onDelete('cascade');
            $table->string('value', 255);
            $table->timestamps();

            $table->unique(['variant_id', 'category_attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
    }
};
