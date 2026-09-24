<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Cifra 'answers': ahí va texto narrativo del paciente (síntomas, plan,
 * barreras de acceso, observaciones), y todo acceso a esta columna ya se hace
 * en PHP (nunca en SQL), así que no pierde nada quedar ilegible para quien
 * solo tenga acceso a la base de datos.
 *
 * La columna nace 'jsonb' (ver create_clinical_forms_table). El cast
 * 'encrypted:array' de Eloquent guarda una cadena cifrada, no JSON válido, así
 * que primero hay que pasar la columna a TEXT — de ahí el 'USING answers::text'
 * / '::jsonb' en cada sentido, y por qué esto no se puede hacer con
 * Schema::table()->change() a secas. El ALTER es específico de Postgres (es el
 * motor real de la app); en sqlite, que es lo que usan las pruebas, la columna
 * ya no tiene una afinidad de tipo real que cambiar, así que ese paso se salta
 * y solo corre el recifrado de los datos.
 *
 * score, risk_level, egfr, kdigo_g y kdigo_a se quedan sin cifrar: ver el
 * comentario de 2026_09_16_110000_add_renal_result_to_clinical_forms_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('clinical_forms')->whereNotNull('answers')->select('id', 'answers')->get();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_forms ALTER COLUMN answers TYPE text USING answers::text');
        }

        foreach ($rows as $row) {
            DB::table('clinical_forms')
                ->where('id', $row->id)
                ->update(['answers' => Crypt::encryptString($row->answers)]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('clinical_forms')->whereNotNull('answers')->select('id', 'answers')->get();

        foreach ($rows as $row) {
            DB::table('clinical_forms')
                ->where('id', $row->id)
                ->update(['answers' => Crypt::decryptString($row->answers)]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE clinical_forms ALTER COLUMN answers TYPE jsonb USING answers::jsonb');
        }
    }
};
