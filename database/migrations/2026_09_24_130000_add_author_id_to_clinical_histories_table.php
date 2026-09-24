<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada entrada de historia registra quién la escribió.
 *
 * La Res. 1644 de 2026 pide que cada atención quede registrada identificando a
 * quienes participaron. Es nullable solo por las entradas viejas: las nuevas
 * siempre llevan autor, y la migración siguiente recupera el de las viejas
 * desde la auditoría. Índice porque PostgreSQL no lo crea solo, y restrict
 * porque el autor de un registro clínico no puede desaparecer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->index()->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropIndex(['author_id']);
            $table->dropColumn('author_id');
        });
    }
};
