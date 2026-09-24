<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimiento informado de teleconsulta (Resolución 2654 de 2019, derogada
 * por la Res. 1644 de 2026).
 *
 * Es distinto del consentimiento de datos de la Ley 1581, que ya existe: aquel
 * autoriza tratar los datos personales, y este autoriza ser atendido por
 * videollamada, con sus límites y sus riesgos propios (que la conexión falle a
 * mitad de la consulta, que el profesional no pueda examinar físicamente, o que
 * haya que derivar a atención presencial).
 *
 * Se versiona igual que el otro: cuando el texto cambie hay que poder demostrar
 * qué aceptó exactamente cada paciente, y volver a pedirlo a quien aceptó una
 * versión anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('teleconsultation_consent_accepted_at')->nullable()->after('consent_version');
            $table->string('teleconsultation_consent_version')->nullable()->after('teleconsultation_consent_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['teleconsultation_consent_accepted_at', 'teleconsultation_consent_version']);
        });
    }
};
