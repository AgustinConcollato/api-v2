<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE category_attributes MODIFY type ENUM('text','number','select','boolean') NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE category_attributes MODIFY type ENUM('text','number','select') NOT NULL DEFAULT 'text'");
    }
};
