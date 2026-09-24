<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nivel de riesgo de seguimiento remoto que asigna el médico
 * (Res. 1644 de 2026, art. 19 par. 1).
 *
 * Es un dato clínico: va cifrado desde la aplicación, así que la columna es
 * texto y no un enum de la base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('follow_up_risk_level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('follow_up_risk_level');
        });
    }
};
