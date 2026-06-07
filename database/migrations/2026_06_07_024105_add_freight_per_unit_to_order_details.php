<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('freight_per_unit', 10, 2)->default(0)->after('purchase_price');
        });
        // Backfill: registros existentes tenían 5% hardcodeado
        DB::table('order_details')->update([
            'freight_per_unit' => DB::raw('purchase_price * 0.05'),
        ]);
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('freight_per_unit');
        });
    }
};
