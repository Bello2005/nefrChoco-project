<?php

use App\Services\PatientIdentityService;
use Illuminate\Database\Migrations\Migration;

/**
 * Completa la identidad de las fichas existentes SIN ADIVINAR.
 *
 * - Nombres: se proponen separados con una heurística y la ficha queda
 *   marcada para revisión.
 * - Tipo de documento y municipio: se mapean solo si coinciden exactamente
 *   con el catálogo (sin tildes ni mayúsculas; entre municipios homónimos se
 *   prefiere el del Chocó). Lo demás queda marcado.
 *
 * Si los catálogos todavía no están importados, todo queda marcado. Después
 * de importarlos se vuelve a correr con `php artisan pacientes:revisar-identidad`.
 *
 * Usa el servicio y no SQL propio para que la migración y el comando apliquen
 * exactamente la misma regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PatientIdentityService::class)->backfillAll();
    }

    /**
     * No se deshace: los nombres propuestos siguen marcados para revisión y
     * la migración anterior borra las columnas si se revierte.
     */
    public function down(): void
    {
        //
    }
};
