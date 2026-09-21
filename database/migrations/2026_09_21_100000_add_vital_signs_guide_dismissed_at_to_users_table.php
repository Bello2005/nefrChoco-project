<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de que el paciente ya descartó la guía de "cómo tomarte la medición".
 *
 * Va en la base y no en el navegador porque en el territorio es común compartir
 * teléfono o reinstalar la aplicación: con almacenamiento local, la guía
 * reaparecería una y otra vez a quien ya la leyó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vital_signs_guide_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('vital_signs_guide_dismissed_at');
        });
    }
};
