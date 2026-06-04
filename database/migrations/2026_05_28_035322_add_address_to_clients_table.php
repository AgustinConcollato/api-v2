<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('street');
            $table->string('street_number');
            $table->string('floor')->nullable();
            $table->string('apartment')->nullable();
            $table->string('locality');
            $table->string('province');
            $table->string('postal_code', 10);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
