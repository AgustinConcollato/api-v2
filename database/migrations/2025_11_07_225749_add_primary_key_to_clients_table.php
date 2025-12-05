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
        // Use Schema::table to modify the existing 'clients' table
        Schema::table('clients', function (Blueprint $table) {
            // Set the existing 'id' column as the primary key
            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is tricky because we need to drop the primary key constraint
        Schema::table('clients', function (Blueprint $table) {
            // Drop the primary key constraint
            // The name is typically the primary key column name
            $table->dropPrimary(['id']); 
        });
    }
};