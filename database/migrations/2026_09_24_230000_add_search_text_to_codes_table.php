<?php

use App\Models\Code;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Búsqueda de catálogos sin importar tildes.
 *
 * Los archivos reales de SISPRO no son parejos: la CIE-10 viene sin tildes y
 * los CUPS con tildes, así que buscar sobre `display` fallaba según cómo
 * escribiera el médico. `search_text` guarda código y nombre sin tildes y en
 * minúsculas (Code::searchText), y el índice pg_trgm pasa de `display` a esta
 * columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codes', function (Blueprint $table) {
            $table->text('search_text')->nullable();
        });

        // Los catálogos que ya estén importados se rellenan aquí para no tener
        // que volver a importarlos.
        DB::table('codes')->select(['id', 'code', 'display'])->orderBy('id')->chunkById(1000, function ($codes) {
            foreach ($codes as $code) {
                DB::table('codes')->where('id', $code->id)->update(['search_text' => Code::searchText($code->code, $code->display)]);
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS codes_display_trgm_index');

            try {
                // Igual que en la migración de catálogos: si no se puede usar
                // pg_trgm, queda un índice normal y la búsqueda sigue.
                DB::transaction(function () {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                    DB::statement('CREATE INDEX codes_search_text_trgm_index ON codes USING gin (search_text gin_trgm_ops)');
                });

                return;
            } catch (Throwable) {
                // Sin permiso para la extensión: índice normal.
            }
        }

        Schema::table('codes', function (Blueprint $table) {
            $table->index('search_text');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS codes_search_text_trgm_index');
        }

        if (Schema::hasIndex('codes', 'codes_search_text_index')) {
            Schema::table('codes', fn (Blueprint $table) => $table->dropIndex('codes_search_text_index'));
        }

        Schema::table('codes', fn (Blueprint $table) => $table->dropColumn('search_text'));

        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::transaction(fn () => DB::statement('CREATE INDEX codes_display_trgm_index ON codes USING gin (display gin_trgm_ops)'));
            } catch (Throwable) {
                // Sin pg_trgm ya existía el índice normal sobre display.
            }
        }
    }
};
