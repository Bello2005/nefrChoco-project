<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorización del titular para el tratamiento de sus datos (Ley 1581 de 2012).
 *
 * Se guarda la versión del texto aceptado, no solo la fecha: cuando la política
 * cambie hay que poder demostrar qué fue exactamente lo que autorizó cada
 * paciente, y volver a pedir consentimiento a quienes aceptaron una versión previa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('consent_accepted_at')->nullable()->after('user_id');
            $table->string('consent_version')->nullable()->after('consent_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['consent_accepted_at', 'consent_version']);
        });
    }
};
