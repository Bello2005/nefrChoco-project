<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El consentimiento de teleconsulta se puede retirar (Res. 1644 de 2026, art. 7).
 *
 * Se guarda cuándo se retiró en vez de borrar la aceptación: la fecha en que
 * la persona autorizó sigue siendo un hecho que respalda las teleconsultas
 * que ya se hicieron con esa autorización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('teleconsultation_consent_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('teleconsultation_consent_revoked_at');
        });
    }
};
