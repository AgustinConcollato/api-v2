<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Clientes creados antes del sistema de cuentas tienen contraseñas
        // random que nunca conocieron — las nulleamos para que puedan registrarse.
        DB::table('clients')->update(['password' => null]);
    }

    public function down(): void
    {
        // Revertir a NOT NULL requeriría un valor por defecto; se omite.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
