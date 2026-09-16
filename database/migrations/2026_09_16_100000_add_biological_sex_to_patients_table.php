<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sexo biológico de la ficha del paciente.
 *
 * Se agrega porque las fórmulas de función renal lo necesitan como variable:
 * CKD-EPI 2021 aplica un coeficiente distinto según el sexo. No es un campo de
 * identidad de género y solo admite los dos valores que la fórmula contempla.
 *
 * Queda nullable a propósito: las fichas ya registradas no tienen el dato y no
 * se puede inventar ni deducir de ningún otro campo. Es obligatorio de ahora en
 * adelante al crear o editar una ficha, la ficha avisa cuando falta, y el
 * seguimiento de enfermedad renal se bloquea hasta que alguien lo complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('biological_sex')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('biological_sex');
        });
    }
};
