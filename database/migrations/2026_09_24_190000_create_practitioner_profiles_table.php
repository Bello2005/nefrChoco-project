<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos profesionales del médico para el RDA del IHCE: el profesional debe
 * estar activo en RETHUS.
 *
 * El documento va sin cifrar y con índice único, igual que el del paciente:
 * identifica a la persona y no puede repetirse. Lo demás va cifrado desde la
 * aplicación (texto). La fecha y el autor de la verificación RETHUS quedan
 * sin cifrar porque son metadatos de control, no datos de la persona, y la
 * nota de la verificación sí va cifrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 50)->nullable()->unique();
            $table->text('profession')->nullable();
            $table->text('professional_registration')->nullable();
            $table->text('specialty')->nullable();
            $table->timestamp('rethus_verified_at')->nullable();
            $table->foreignId('rethus_verified_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->text('rethus_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_profiles');
    }
};
