<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aclaraciones a notas de teleconsulta ya cerradas.
 *
 * Una nota cerrada no se edita: la auditoría guarda qué campos cambió cada
 * quien, no sus valores, así que reescribirla borraría la versión anterior
 * sin rastro. Para corregirla se agrega una aclaración con autor y fecha, y la
 * nota original se conserva.
 *
 * Las dos llaves llevan índice porque PostgreSQL no lo crea solo, y restrict
 * porque una aclaración es registro clínico: ni la teleconsulta ni su autor se
 * pueden borrar mientras ella exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teleconsultation_clarifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teleconsultation_id')->index()->constrained('teleconsultations')->restrictOnDelete();
            $table->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            // Cifrado desde la aplicación: queda como texto ilegible en reposo.
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teleconsultation_clarifications');
    }
};
