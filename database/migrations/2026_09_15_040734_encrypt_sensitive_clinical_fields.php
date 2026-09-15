<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara las columnas que se guardarán cifradas (Ley 1581 de 2012).
 *
 * El texto cifrado de Laravel ocupa mucho más que el original y es de longitud
 * variable, así que estas columnas pasan a TEXT. Solo se cifran campos que la
 * aplicación nunca filtra ni ordena: cifrar `document_number` o `full_name`
 * rompería la búsqueda de pacientes y el índice único del documento, porque
 * cada cifrado usa un IV distinto y produce un valor diferente cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('phone')->change();
            $table->text('emergency_contact_name')->nullable()->change();
            $table->text('emergency_contact_phone')->nullable()->change();
        });

        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->text('ecnt_diagnosis')->nullable()->change();
        });

        Schema::table('teleconsultations', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('phone')->change();
            $table->string('emergency_contact_name')->nullable()->change();
            $table->string('emergency_contact_phone')->nullable()->change();
        });

        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->string('ecnt_diagnosis')->nullable()->change();
        });

        Schema::table('teleconsultations', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });
    }
};
