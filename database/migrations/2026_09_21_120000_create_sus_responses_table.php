<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuestas al cuestionario SUS de usabilidad.
 *
 * Se guarda `user_id` únicamente para evitar que una misma persona responda dos
 * veces y desbalancee la muestra; el reporte agrega por rol y nunca muestra
 * nombres. Quien contesta que la plataforma le resultó incómoda no debería
 * quedar señalado ante quien administra el sistema.
 *
 * El puntaje se guarda calculado además de las respuestas: el reporte promedia
 * sin recalcular diez ítems por fila, y las respuestas quedan por si hay que
 * revisar la fórmula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sus_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Rol al momento de responder: la usabilidad se lee por tipo de
            // usuario, y el rol de la cuenta puede cambiar después.
            $table->string('role');
            $table->json('answers');
            $table->decimal('score', 4, 1);
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sus_responses');
    }
};
