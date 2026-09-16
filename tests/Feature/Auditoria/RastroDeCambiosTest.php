<?php

use App\Enums\VitalSignType;
use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Rastro de cambios sobre datos clínicos (Ley 1581 de 2012).
 *
 * Dos exigencias que se contrapesan: el registro tiene que demostrar qué se
 * modificó, y a la vez no puede convertirse en una copia sin cifrar de lo que
 * se cifró en la tabla de origen. `activity_log.properties` es JSON en claro.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

/** Todo el contenido de la tabla de auditoría, tal como queda escrito. */
function auditoriaEnCrudo(): string
{
    return DB::table('activity_log')->pluck('properties')->implode(' ');
}

test('crear un paciente deja registrado qué quedó guardado', function () {
    $patient = Patient::factory()->create(['full_name' => 'Rosalba Mosquera']);

    $activity = Activity::where('subject_type', Patient::class)
        ->where('subject_id', $patient->id)
        ->latest('id')
        ->first();

    // Antes properties venía vacío: constaba que alguien tocó la ficha, pero
    // no qué se había guardado.
    expect($activity->properties)->not->toBeEmpty();
    expect($activity->properties['campos'])->toContain('full_name');
});

test('el rastro guarda el nombre del campo pero nunca el dato cifrado', function () {
    Patient::factory()->create(['phone' => '3009998877']);

    $activity = Activity::where('subject_type', Patient::class)->latest('id')->first();

    // Consta que el teléfono forma parte de lo que se guardó...
    expect($activity->properties['campos'])->toContain('phone');
    // ...pero el número en sí no aparece por ninguna parte de la auditoría.
    expect(auditoriaEnCrudo())->not->toContain('3009998877');
});

test('la historia clínica deja rastro sin copiar el contenido clínico', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($this->medico)->post(route('medico.pacientes.historia-clinica.store', $patient), [
        'ecnt_diagnosis' => 'DIAGNOSTICO-RESERVADO-XYZ',
        'medical_history' => 'ANTECEDENTE-RESERVADO-XYZ',
        'allergies' => null,
        'current_medication' => null,
    ]);

    $activity = Activity::where('subject_type', ClinicalHistory::class)->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->medico->id);
    expect(auditoriaEnCrudo())->not->toContain('RESERVADO');
});

test('cerrar una teleconsulta deja rastro y las notas no se duplican en claro', function () {
    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);

    $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $appointment), [
        'notes' => 'HALLAZGO-CLINICO-RESERVADO',
    ]);

    $activity = Activity::where('subject_type', Teleconsultation::class)->latest('id')->first();

    // Era la única escritura clínica del sistema sin ningún rastro.
    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->medico->id);
    expect(auditoriaEnCrudo())->not->toContain('HALLAZGO-CLINICO-RESERVADO');
});

test('una medición registra tipo y valor pero no la nota cifrada', function () {
    $patient = Patient::factory()->create();

    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::Glucose->value,
        'value' => 155,
        'unit' => 'mg/dL',
        'notes' => 'NOTA-PRIVADA-DEL-PACIENTE',
    ]);

    $activity = Activity::where('subject_type', VitalSign::class)->latest('id')->first();

    expect($activity->properties['campos'])->toContain('type');
    expect($activity->properties['campos'])->toContain('value');
    expect(auditoriaEnCrudo())->not->toContain('NOTA-PRIVADA-DEL-PACIENTE');
});

test('las respuestas crudas de un formulario no se copian a la auditoría', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($this->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $patient->id,
        'form_type' => 'adherencia_tratamiento',
        'answers' => [
            'olvida_medicamento' => 'si',
            'toma_hora_indicada' => 'no',
            'suspende_si_mejora' => 'no',
            'suspende_si_mal' => 'no',
            'barreras' => 'BARRERA-PERSONAL-RESERVADA',
        ],
    ]);

    $activity = Activity::where('subject_type', ClinicalForm::class)->latest('id')->first();

    expect($activity->properties['campos'])->toContain('risk_level');
    expect(auditoriaEnCrudo())->not->toContain('BARRERA-PERSONAL-RESERVADA');
});
