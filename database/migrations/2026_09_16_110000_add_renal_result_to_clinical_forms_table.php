<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado del seguimiento renal, congelado junto al formulario.
 *
 * Se guarda la TFGe calculada y las categorías KDIGO en lugar de recalcularlas
 * al consultar: si la ecuación cambia —CKD-EPI ya cambió en 2021 al eliminar el
 * coeficiente de raza— el histórico debe seguir mostrando lo que se concluyó
 * ese día, no lo que diría la fórmula de hoy.
 *
 * Sin cifrar, por el mismo criterio que `score` y `risk_level`: son un número y
 * dos códigos cortos que se grafican, se comparan entre controles y se filtran,
 * no contenido clínico narrativo.
 */
// TODO doc: el ERD de arquitectura.md no incluye egfr, kdigo_g ni kdigo_a
// en clinical_forms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->unsignedSmallInteger('egfr')->nullable()->after('risk_level');
            $table->string('kdigo_g')->nullable()->after('egfr');
            $table->string('kdigo_a')->nullable()->after('kdigo_g');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->dropColumn(['egfr', 'kdigo_g', 'kdigo_a']);
        });
    }
};
