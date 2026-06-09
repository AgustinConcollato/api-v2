<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'second_unit_percentage'])->nullable()->after('product_id');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('discount_value');
            $table->unsignedInteger('min_quantity')->nullable()->after('max_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('promotion_product', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'max_discount_amount', 'min_quantity']);
        });
    }
};
