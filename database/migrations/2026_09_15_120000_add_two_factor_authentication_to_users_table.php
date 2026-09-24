<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo factor de autenticación (Resolución 2654 de 2019, derogada por la
 * Res. 1644 de 2026).
 *
 * El secreto TOTP y los códigos de recuperación se guardan cifrados: quien
 * llegue a leer la base sin la APP_KEY no puede generar códigos válidos ni
 * suplantar a un profesional frente a la historia clínica.
 *
 * `two_factor_confirmed_at` separa "empezó a configurarlo" de "lo verificó".
 * Sin esa marca el segundo factor no se exige todavía, para que nadie quede
 * fuera de su propia cuenta por abandonar el proceso a la mitad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
