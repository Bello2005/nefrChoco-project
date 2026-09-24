<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad y datos mínimos de la Res. 866 de 2021.
 *
 * - Nombres separados, sin cifrar por la misma razón que full_name: el RDA
 *   exige que coincidan con el registro nacional (primer nombre y primer
 *   apellido) y se buscan en SQL. full_name se sigue guardando.
 * - municipality_code (DIVIPOLA), sin cifrar como municipality: se usa para
 *   reportes por territorio.
 * - Los datos sociodemográficos nuevos son sensibles y van cifrados desde la
 *   aplicación, por eso son texto.
 * - identity_review_pending / identity_review_reasons: fichas cuyos datos no
 *   se pudieron completar sin adivinar y que alguien tiene que revisar.
 *
 * Todo nullable: las fichas existentes no tienen estos datos y se completan
 * desde "Fichas por revisar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('first_surname')->nullable();
            $table->string('second_surname')->nullable();
            $table->string('municipality_code', 20)->nullable()->index();

            $table->text('gender_identity')->nullable();
            $table->text('ethnicity')->nullable();
            $table->text('disability')->nullable();
            $table->text('occupation')->nullable();
            $table->text('residence_zone')->nullable();
            $table->text('eapb_code')->nullable();
            $table->text('affiliation_type')->nullable();

            $table->boolean('identity_review_pending')->default(false)->index();
            $table->json('identity_review_reasons')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['municipality_code']);
            $table->dropIndex(['identity_review_pending']);
            $table->dropColumn([
                'first_name', 'middle_name', 'first_surname', 'second_surname', 'municipality_code',
                'gender_identity', 'ethnicity', 'disability', 'occupation', 'residence_zone', 'eapb_code', 'affiliation_type',
                'identity_review_pending', 'identity_review_reasons',
            ]);
        });
    }
};
