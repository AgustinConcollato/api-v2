<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Migrar datos existentes a los 3 valores nuevos
        // draft → incomplete
        DB::statement("UPDATE products SET status = 'incomplete' WHERE status = 'draft'");
        // pending_prices → incomplete
        DB::statement("UPDATE products SET status = 'incomplete' WHERE status = 'pending_prices'");
        // pending_barcode: si tiene precios en todas las listas → published, si no → incomplete
        // Primero marcamos todos pending_barcode como incomplete, luego publicamos los que tienen todos los precios
        DB::statement("UPDATE products SET status = 'incomplete' WHERE status = 'pending_barcode'");

        $totalLists = DB::table('price_lists')->count();
        if ($totalLists > 0) {
            DB::statement("
                UPDATE products p
                SET p.status = 'published'
                WHERE p.status = 'incomplete'
                AND (
                    SELECT COUNT(DISTINCT lp.price_list_id)
                    FROM list_product lp
                    WHERE lp.product_id = p.id
                ) >= ?
            ", [$totalLists]);
        }

        // 2. Alterar el enum para que solo acepte los 3 valores
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status ENUM('incomplete','published','archived') NOT NULL DEFAULT 'incomplete'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN status ENUM('draft','incomplete','pending_images','pending_prices','pending_barcode','published','archived') NOT NULL DEFAULT 'incomplete'
        ");
    }
};
