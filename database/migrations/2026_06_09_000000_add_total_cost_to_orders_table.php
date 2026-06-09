<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_cost', 10, 2)->nullable()->after('final_total_amount');
        });

        DB::statement("
            UPDATE orders o
            SET total_cost = (
                SELECT COALESCE(SUM((od.purchase_price + od.freight_per_unit) * od.quantity), 0)
                FROM order_details od
                WHERE od.order_id = o.id
            )
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
