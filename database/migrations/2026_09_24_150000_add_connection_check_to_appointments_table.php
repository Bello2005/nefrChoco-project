<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Último resultado de "Probar mi conexión" del paciente para esta cita
 * (Res. 1644 de 2026, art. 24.8 y art. 33).
 *
 * Solo el nivel y la fecha: nada clínico, así que no va cifrado. Le sirve al
 * médico para saber, antes de abrir la sala, si conviene empezar con audio o
 * pasar directo a una llamada telefónica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('connection_check_level', 20)->nullable();
            $table->timestamp('connection_check_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['connection_check_level', 'connection_check_at']);
        });
    }
};
