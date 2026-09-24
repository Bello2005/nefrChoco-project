<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos oficiales versionados (CIE-10, CIE-11, CUPS, DIVIPOLA, EAPB, tipos
 * de documento y los CodeSystem/ValueSet de la guía FHIR del IHCE).
 *
 * Son datos públicos de referencia: no se cifran. Los códigos nunca se borran;
 * los que desaparecen de una versión nueva quedan inactivos, porque hay
 * registros históricos que los siguen usando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_systems', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('version')->nullable();
            // De dónde salió el archivo (texto libre) y su huella, para poder
            // demostrar qué versión oficial se importó.
            $table->text('source')->nullable();
            $table->string('source_sha256', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_system_id')->index()->constrained('code_systems')->restrictOnDelete();
            $table->string('code', 100);
            $table->text('display');
            $table->string('parent_code', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['code_system_id', 'code']);
        });

        $this->addDisplaySearchIndex();
    }

    /**
     * Búsqueda por nombre: con pg_trgm si el servidor deja crear la extensión
     * (un ILIKE '%texto%' la aprovecha); si no, un índice normal.
     * CodeCatalog::searchMode() dice cuál quedó, y la pantalla de admin lo
     * muestra.
     */
    private function addDisplaySearchIndex(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            try {
                // Transacción propia: si CREATE EXTENSION falla por permisos,
                // solo se deshace este intento y la migración sigue.
                DB::transaction(function () {
                    DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
                    DB::statement('CREATE INDEX codes_display_trgm_index ON codes USING gin (display gin_trgm_ops)');
                });

                return;
            } catch (Throwable) {
                // Sin permiso para la extensión: índice normal.
            }
        }

        Schema::table('codes', function (Blueprint $table) {
            $table->index('display');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes');
        Schema::dropIfExists('code_systems');
    }
};
