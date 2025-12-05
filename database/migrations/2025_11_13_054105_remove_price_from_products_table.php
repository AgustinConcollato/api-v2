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
        Schema::table('products', function (Blueprint $table) {
            // ELIMINAR la columna 'price'
            $table->dropColumn('price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * ¡Importante! Debes definir aquí cómo era la columna
     * antes de eliminarla para poder revertir la migración.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // AÑADIR la columna 'price' de nuevo
            // Ajusta el tipo de dato (decimal, float, integer) y NOT NULL si es necesario
            $table->decimal('price', 10, 2)->nullable(false)->after('sku');
        });
    }
};
