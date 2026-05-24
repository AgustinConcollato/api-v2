<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_attribute_id')
                ->constrained('category_attributes')
                ->onDelete('cascade');
            $table->string('value', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_attribute_options');
    }
};
