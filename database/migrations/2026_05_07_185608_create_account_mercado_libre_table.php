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
        Schema::create('account_mercado_libre', function (Blueprint $table) {
            $table->id();

            // Relación con tu tabla de usuarios
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // ID de la cuenta de Mercado libre (ej: 123456789)
            $table->string('ml_user_id')->index();

            // Tokens de OAuth
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('token_type')->default('Bearer');
            $table->string('public_key')->nullable();

            // Permisos
            $table->text('scope')->nullable();

            // Modo en que se creó (true = producción, false = test)
            $table->boolean('live_mode')->default(false);

            // Fecha/hora de expiración del access_token
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_mercado_libre');
    }
};
