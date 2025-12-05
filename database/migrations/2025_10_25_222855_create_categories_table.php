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
        Schema::create('categories', function (Blueprint $table) {
            $table->id(); // ID
            $table->string('name'); // NAME
            $table->string('slug')->unique(); // SLUG (usado para URLs amigables)
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null'); // PARENT_ID (para categorías anidadas)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
