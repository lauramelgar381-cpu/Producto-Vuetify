<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Estado de la cuenta: una cuenta inactiva no puede iniciar sesión
            $table->boolean('active')->default(true);
            // true = tiene una clave temporal y debe cambiarla al entrar
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['active', 'must_change_password']);
        });
    }
};
