<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las cuentas se desactivan en vez de borrarse.
 *
 * Borrar a un médico arrastraba sus citas y sus notas, y borrar a un paciente
 * las mediciones que registró. Una cuenta desactivada sigue existiendo para
 * todo lo que firmó; solo deja de poder entrar. No se usa SoftDeletes en User
 * porque chocaría con la unicidad del correo y dejaría a las notas sin autor
 * visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
