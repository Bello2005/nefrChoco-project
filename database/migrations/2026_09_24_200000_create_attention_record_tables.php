<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro estructurado de la atención para el RDA y los RIPS.
 *
 * La atención es la cita (presencial o teleconsulta). Todo lo clínico va
 * cifrado desde la aplicación (columnas de texto), cada fila guarda su autor,
 * y todas las llaves son restrict con índice: nada de esto se borra en
 * cascada. Los códigos se validan contra el catálogo activo antes de
 * cifrarse, porque cifrados ya no se pueden comparar en SQL.
 *
 * Nada se edita después del cierre. Una corrección es una fila nueva que
 * reemplaza a la anterior (replaces_id) y viene de una aclaración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('consultation_reason')->nullable();
            $table->text('purpose')->nullable();
            $table->text('external_cause')->nullable();
        });

        Schema::create('appointment_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->index()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('cie10_code');
            // Codificación dual durante la transición a CIE-11 (Res. 1442 de 2024).
            $table->text('cie11_code')->nullable();
            // Estructural, no clínico: lo cuentan los reportes (¿hay principal?).
            $table->string('role', 20);
            $table->text('diagnosis_type')->nullable();
            $table->foreignId('replaces_id')->nullable()->index()->constrained('appointment_diagnoses')->restrictOnDelete();
            $table->foreignId('teleconsultation_clarification_id')->nullable()->index()->constrained('teleconsultation_clarifications')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('appointment_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->index()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('cups_code');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('appointment_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->index()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('description');
            $table->text('dose')->nullable();
            $table->text('frequency')->nullable();
            // TODO: catálogo oficial de medicamentos. No se inventa: queda nullable.
            $table->text('code')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_allergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->index()->constrained('patients')->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->index()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            // El RDA distingue "no tiene alergias conocidas" de "no se preguntó":
            // la primera es una fila con esta marca; la segunda, ninguna fila.
            $table->boolean('no_known_allergies')->default(false);
            $table->text('substance')->nullable();
            $table->text('allergy_type')->nullable();
            $table->text('status')->nullable();
            $table->timestamps();
        });

        Schema::create('clinical_history_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_history_id')->index()->constrained('clinical_histories')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('cie10_code');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_history_diagnoses');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('appointment_medications');
        Schema::dropIfExists('appointment_procedures');
        Schema::dropIfExists('appointment_diagnoses');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['consultation_reason', 'purpose', 'external_cause']);
        });
    }
};
