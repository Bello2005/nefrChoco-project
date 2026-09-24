<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recupera el autor de las entradas de historia escritas antes de que
 * clinical_histories guardara author_id.
 *
 * LogsChangedFields deja un evento 'created' por cada entrada, y spatie
 * activitylog le pone como causer al usuario autenticado que la creó. Solo
 * datos: va aparte de la migración que agrega la columna.
 *
 * El EXISTS importa: si el autor fue un usuario que ya se borró, la llave
 * foránea nueva rechazaría el UPDATE y el despliegue fallaría. Esas entradas
 * se quedan sin autor y la pantalla lo dice.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::update(<<<'SQL'
            UPDATE clinical_histories
            SET author_id = (
                SELECT a.causer_id FROM activity_log a
                WHERE a.subject_type = ?
                  AND a.subject_id = clinical_histories.id
                  AND a.event = 'created'
                  AND a.causer_type = ?
                  AND EXISTS (SELECT 1 FROM users u WHERE u.id = a.causer_id)
                ORDER BY a.id
                LIMIT 1
            )
            WHERE author_id IS NULL
            SQL, ['App\Models\ClinicalHistory', 'App\Models\User']);
    }

    /**
     * No se deshace: el autor recuperado es un dato verdadero, sacado del
     * rastro de auditoría. Borrarlo al revertir solo quitaría información
     * correcta; si se revierte también la migración anterior, la columna
     * desaparece con ella.
     */
    public function down(): void
    {
        //
    }
};
