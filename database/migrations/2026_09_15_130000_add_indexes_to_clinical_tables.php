<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de consulta sobre las tablas clínicas.
 *
 * PostgreSQL no indexa las claves foráneas por su cuenta: antes de esta
 * migración `appointments`, `clinical_forms` y `clinical_histories` solo
 * tenían la clave primaria, así que cada panel resolvía por recorrido
 * secuencial de la tabla completa.
 *
 * Cada índice de aquí corresponde a una consulta real de la aplicación; los
 * compuestos siguen el orden en que se filtra y después se ordena.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Agenda del médico y tendencia semanal: filtra por profesional y
            // ordena por fecha.
            $table->index(['doctor_id', 'scheduled_at'], 'appointments_doctor_scheduled_index');

            // Citas del paciente y búsqueda del médico tratante, que toma la
            // cita más reciente de esa persona.
            $table->index(['patient_id', 'scheduled_at'], 'appointments_patient_scheduled_index');

            // Teleconsultas pendientes y conteos por estado.
            $table->index(['status', 'type'], 'appointments_status_type_index');
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            // Series por paciente en orden cronológico (gráficas y telemonitoreo).
            $table->index(['patient_id', 'recorded_at'], 'vital_signs_patient_recorded_index');

            // Panel de alertas: últimas mediciones de todo el programa.
            $table->index('recorded_at', 'vital_signs_recorded_index');
        });

        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->index(['patient_id', 'created_at'], 'clinical_forms_patient_created_index');

            // Filtro por instrumento en el listado de formularios.
            $table->index('form_type', 'clinical_forms_type_index');

            // Nivel de riesgo: lo consulta el apoyo a decisiones clínicas.
            $table->index('risk_level', 'clinical_forms_risk_index');
        });

        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->index(['patient_id', 'created_at'], 'clinical_histories_patient_created_index');
        });

        Schema::table('patients', function (Blueprint $table) {
            // Cobertura territorial del panel administrativo.
            $table->index('municipality', 'patients_municipality_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_doctor_scheduled_index');
            $table->dropIndex('appointments_patient_scheduled_index');
            $table->dropIndex('appointments_status_type_index');
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropIndex('vital_signs_patient_recorded_index');
            $table->dropIndex('vital_signs_recorded_index');
        });

        Schema::table('clinical_forms', function (Blueprint $table) {
            $table->dropIndex('clinical_forms_patient_created_index');
            $table->dropIndex('clinical_forms_type_index');
            $table->dropIndex('clinical_forms_risk_index');
        });

        Schema::table('clinical_histories', function (Blueprint $table) {
            $table->dropIndex('clinical_histories_patient_created_index');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_municipality_index');
        });
    }
};
