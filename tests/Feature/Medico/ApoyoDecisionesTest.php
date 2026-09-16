<?php

use App\Enums\VitalSignType;
use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\ClinicalDecisionSupport;
use Database\Seeders\RoleSeeder;

/**
 * Apoyo a decisiones clínicas por reglas explicables.
 *
 * Cada regla tiene su caso que la dispara y su caso que no, porque una regla
 * que se dispara de más es tan inútil como una que no se dispara: el
 * profesional deja de mirarla.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create(['full_name' => 'Rosalba Mosquera']);
});

function instrumento(Patient $patient, User $medico, string $tipo, string $nivel, int $puntaje, array $respuestas = []): ClinicalForm
{
    return ClinicalForm::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'form_type' => $tipo,
        'risk_level' => $nivel,
        'score' => $puntaje,
        'answers' => $respuestas,
    ]);
}

function medicion(Patient $patient, User $medico, VitalSignType $tipo, float $valor, int $diasAtras): VitalSign
{
    return VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'type' => $tipo->value,
        'value' => $valor,
        'unit' => $tipo->unit(),
        'recorded_at' => now()->subDays($diasAtras),
    ]);
}

function recomendaciones(Patient $patient)
{
    return app(ClinicalDecisionSupport::class)->forPatient($patient->fresh());
}

test('dos mediciones seguidas fuera de rango sí generan recomendación', function () {
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 210, 2);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 195, 5);

    $recs = recomendaciones($this->patient);

    expect($recs)->toHaveCount(1);
    expect($recs->first()->ruleKey)->toBe('signo_vital_sostenido_fuera_de_rango');
    expect($recs->first()->priority->value)->toBe('alta');
});

test('una sola medición alta entre controles normales no genera recomendación', function () {
    // La más reciente está en rango: la racha se corta y no hay tendencia.
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 100, 1);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 210, 4);

    expect(recomendaciones($this->patient))->toBeEmpty();
});

test('las mediciones fuera de la ventana de vigencia se ignoran', function () {
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 210, 90);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 220, 95);

    expect(recomendaciones($this->patient))->toBeEmpty();
});

test('riesgo de diabetes alto más glucemia fuera de rango se cruza en una recomendación', function () {
    instrumento($this->patient, $this->medico, 'tamizaje_diabetes', 'alto', 17);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 190, 3);

    $recs = recomendaciones($this->patient);
    $cruce = $recs->firstWhere('ruleKey', 'riesgo_diabetes_con_evidencia');

    expect($cruce)->not->toBeNull();
    expect($cruce->priority->value)->toBe('alta');
    // La conducta sugerida sale del instrumento, no la inventa la regla.
    expect($cruce->action)->toContain('Derivar');
});

test('riesgo de diabetes alto más no adherencia también se cruza', function () {
    instrumento($this->patient, $this->medico, 'tamizaje_diabetes', 'alto', 16);
    instrumento($this->patient, $this->medico, 'adherencia_tratamiento', 'no_adherente', 4);

    $recs = recomendaciones($this->patient);

    expect($recs->pluck('ruleKey')->all())->toContain('riesgo_diabetes_con_evidencia');
});

test('el tamizaje alto por sí solo no genera recomendación', function () {
    // Sin una segunda señal, el resultado ya lo comunica el propio instrumento.
    instrumento($this->patient, $this->medico, 'tamizaje_diabetes', 'alto', 17);

    expect(recomendaciones($this->patient))->toBeEmpty();
});

test('no adherencia sobre una ECNT diagnosticada genera intervención educativa', function () {
    ClinicalHistory::factory()->create([
        'patient_id' => $this->patient->id,
        'ecnt_diagnosis' => 'Hipertensión arterial',
    ]);
    instrumento($this->patient, $this->medico, 'adherencia_tratamiento', 'no_adherente', 4, [
        'barreras' => 'Vive a tres horas en lancha del puesto de salud.',
    ]);

    $rec = recomendaciones($this->patient)->firstWhere('ruleKey', 'no_adherencia_con_ecnt_diagnosticada');

    expect($rec)->not->toBeNull();
    expect($rec->priority->value)->toBe('media');
    // La barrera que escribió el profesional viaja al motivo: es lo accionable.
    expect($rec->reason)->toContain('lancha');
    expect($rec->reason)->toContain('Hipertensión arterial');
});

test('no adherencia sin ECNT diagnosticada no genera esa recomendación', function () {
    instrumento($this->patient, $this->medico, 'adherencia_tratamiento', 'no_adherente', 4);

    expect(recomendaciones($this->patient)->pluck('ruleKey')->all())
        ->not->toContain('no_adherencia_con_ecnt_diagnosticada');
});

test('toda recomendación explica con qué dato se disparó', function () {
    ClinicalHistory::factory()->create([
        'patient_id' => $this->patient->id,
        'ecnt_diagnosis' => 'Diabetes mellitus tipo 2',
    ]);
    instrumento($this->patient, $this->medico, 'tamizaje_diabetes', 'alto', 17);
    instrumento($this->patient, $this->medico, 'adherencia_tratamiento', 'no_adherente', 4);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 210, 1);
    medicion($this->patient, $this->medico, VitalSignType::Glucose, 205, 3);

    $recs = recomendaciones($this->patient);

    expect($recs)->toHaveCount(3);

    foreach ($recs as $rec) {
        // Nada de caja negra: motivo y acción siempre presentes.
        expect(trim($rec->reason))->not->toBeEmpty();
        expect(trim($rec->action))->not->toBeEmpty();
        expect($rec->ruleKey)->not->toBeEmpty();
    }
});

test('las recomendaciones se ordenan poniendo primero la prioridad más alta', function () {
    ClinicalHistory::factory()->create([
        'patient_id' => $this->patient->id,
        'ecnt_diagnosis' => 'Enfermedad renal crónica',
    ]);
    instrumento($this->patient, $this->medico, 'adherencia_tratamiento', 'no_adherente', 4);
    medicion($this->patient, $this->medico, VitalSignType::OxygenSaturation, 85, 1);
    medicion($this->patient, $this->medico, VitalSignType::OxygenSaturation, 86, 3);

    $recs = recomendaciones($this->patient);

    expect($recs->first()->priority->value)->toBe('alta');
    expect($recs->last()->priority->value)->toBe('media');
});

test('un paciente sin señales no genera ninguna recomendación', function () {
    expect(recomendaciones($this->patient))->toBeEmpty();
});

test('la franja de prioritarios solo trae pacientes del propio médico', function () {
    $otroMedico = User::factory()->create();
    $otroMedico->assignRole('medico');

    $mio = Patient::factory()->create(['full_name' => 'Paciente Propio']);
    Appointment::factory()->create(['patient_id' => $mio->id, 'doctor_id' => $this->medico->id]);
    medicion($mio, $this->medico, VitalSignType::Glucose, 210, 1);
    medicion($mio, $this->medico, VitalSignType::Glucose, 200, 3);

    $ajeno = Patient::factory()->create(['full_name' => 'Paciente Ajeno']);
    Appointment::factory()->create(['patient_id' => $ajeno->id, 'doctor_id' => $otroMedico->id]);
    medicion($ajeno, $otroMedico, VitalSignType::Glucose, 230, 1);
    medicion($ajeno, $otroMedico, VitalSignType::Glucose, 240, 3);

    $franja = app(ClinicalDecisionSupport::class)->priorityPatients($this->medico);

    expect($franja->pluck('patientName')->all())->toContain('Paciente Propio');
    expect($franja->pluck('patientName')->all())->not->toContain('Paciente Ajeno');
});
