<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('patient_id')->constrained('users')->nullOnDelete();
            // Se guarda el resultado calculado, no solo las respuestas: los umbrales del
            // instrumento pueden cambiar y la historia debe conservar lo que se concluyó ese día.
            $table->unsignedSmallInteger('score')->nullable()->after('answers');
            $table->string('risk_level')->nullable()->after('score');
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['score', 'risk_level']);
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
