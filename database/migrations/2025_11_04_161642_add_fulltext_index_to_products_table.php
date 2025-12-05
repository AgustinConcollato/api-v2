<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        // Si el índice tiene nombre automático, Laravel no lo guarda.
        // Lo removemos usando SQL crudo.
        DB::statement('ALTER TABLE products DROP INDEX products_name_fulltext');
    }
};
