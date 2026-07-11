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
        Schema::table('supplier_purchases', function (Blueprint $table) {
            // Días antes del vencimiento para enviar el recordatorio (null = sin aviso).
            $table->unsignedInteger('reminder_days')->nullable()->default(5)->after('note');
            // Marca de que ya se envió el recordatorio (evita reenvíos).
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->dropColumn(['reminder_days', 'reminder_sent_at']);
        });
    }
};
