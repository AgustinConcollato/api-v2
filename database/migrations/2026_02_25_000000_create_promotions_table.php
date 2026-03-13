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
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->text('description')->nullable();

            // Fecha/hora de vigencia
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable(); // null = tiempo ilimitado

            $table->boolean('is_active')->default(true);

            // Tipo de descuento:
            // - percentage            => discount_value = porcentaje (ej. 10.00 para 10%)
            // - fixed_amount          => discount_value = monto fijo (ej. 6000.00)
            // - second_unit_percentage => descuento en la segunda unidad (ej. 50.00 para 50% off)
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'second_unit_percentage']);
            $table->decimal('discount_value', 10, 2);

            // Monto máximo de descuento que se puede aplicar
            // Ej: 10% con tope de 6000
            $table->decimal('max_discount_amount', 10, 2)->nullable();

            // Cantidad mínima para aplicar la promo (útil para 2da unidad, etc.)
            $table->unsignedInteger('min_quantity')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};

