<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La base de datos deja de borrar datos clínicos en cascada.
 *
 * Con cascadeOnDelete, borrar un usuario, una ficha o una cita se llevaba por
 * delante citas, notas, mediciones y formularios, y como eso ocurre dentro de
 * PostgreSQL, LogsChangedFields no se enteraba: no quedaba rastro de lo que se
 * perdió. Con restrict, la base rechaza el borrado mientras haya registro
 * clínico colgando, y la aplicación ya no ofrece esos borrados (las cuentas se
 * desactivan, las citas se cancelan y las fichas solo se borran de forma
 * lógica).
 *
 * clinical_forms.recorded_by también pasa a restrict: con nullOnDelete, el
 * formulario sobrevivía pero perdía a su autor, y eso tampoco es aceptable en
 * un registro clínico.
 *
 * Quedan fuera a propósito patients.user_id (desvincular una cuenta no debe
 * tocar la ficha) y sus_responses.user_id (no es dato clínico).
 */
return new class extends Migration
{
    /**
     * [tabla, columna, tabla referenciada, comportamiento anterior]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $keys = [
        ['appointments', 'doctor_id', 'users', 'cascade'],
        ['appointments', 'patient_id', 'patients', 'cascade'],
        ['teleconsultations', 'appointment_id', 'appointments', 'cascade'],
        ['vital_signs', 'patient_id', 'patients', 'cascade'],
        ['vital_signs', 'recorded_by', 'users', 'cascade'],
        ['clinical_histories', 'patient_id', 'patients', 'cascade'],
        ['clinical_forms', 'patient_id', 'patients', 'cascade'],
        ['clinical_forms', 'recorded_by', 'users', 'set null'],
    ];

    public function up(): void
    {
        foreach ($this->keys as [$table, $column, $references]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $references) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($references)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->keys as [$table, $column, $references, $previous]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $previous) {
                $blueprint->dropForeign([$column]);
                $blueprint->foreign($column)->references('id')->on($references)->onDelete($previous);
            });
        }
    }
};
