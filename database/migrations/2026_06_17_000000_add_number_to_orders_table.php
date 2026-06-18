<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Columna correlativa autoincremental (no es la PK, que es UUID).
        // MySQL asigna automáticamente valores a las filas existentes y a las nuevas.
        DB::statement('ALTER TABLE orders ADD COLUMN number BIGINT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP COLUMN number');
    }
};
