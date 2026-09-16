<?php

use App\Enums\BiologicalSex;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalDecisionSupport;
use App\Support\ClinicalRules\KidneyFunctionDecline;
use Database\Seeders\RoleSeeder;

/**
 * Regla renal del apoyo a decisiones clínicas.
 *
 * Dos ramas: dónde está el paciente hoy (categoría avanzada) y hacia dónde va
 * (caída acelerada entre controles). La segunda exige dos laboratorios con
 * fechas distintas y separadas: con dos exámenes casi simultáneos, anualizar la
 * diferencia convertiría variabilidad de medición en una alarma.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subYears(60)->format('Y-m-d'),
    ]);
});

function controlDeLaboratorio(Patient $patient, User $medico, int $egfr, int $diasAtras, string $categoriaG, ?string $categoriaA = null): ClinicalForm
{
    return ClinicalForm::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'form_type' => 'seguimiento_erc',
        'answers' => [
            'creatinina' => 1.2,
            'fecha_laboratorio' => now()->subDays($diasAtras)->format('Y-m-d'),
        ],
        'score' => null,
        'risk_level' => null,
        'egfr' => $egfr,
        'kdigo_g' => $categoriaG,
        'kdigo_a' => $categoriaA,
    ]);
}

function recomendacionesDe(Patient $patient)
{
    return app(ClinicalDecisionSupport::class)->forPatient($patient->fresh());
}

function reglaRenalDe(Patient $patient)
{
    return recomendacionesDe($patient)->firstWhere('ruleKey', 'deterioro_funcion_renal');
}

test('una categoría G4 dispara la sugerencia de nefrología sin necesidad de progresión', function () {
    controlDeLaboratorio($this->patient, $this->medico, 22, 20, 'G4');

    $recomendacion = reglaRenalDe($this->patient);

    expect($recomendacion)->not->toBeNull();
    expect($recomendacion->priority->value)->toBe('alta');
    expect($recomendacion->action)->toContain('nefrología');
});

test('una categoría G5 también dispara', function () {
    controlDeLaboratorio($this->patient, $this->medico, 11, 15, 'G5');

    expect(reglaRenalDe($this->patient)?->priority->value)->toBe('alta');
});

test('una albuminuria A3 dispara aunque la TFGe esté conservada', function () {
    controlDeLaboratorio($this->patient, $this->medico, 82, 10, 'G2', 'A3');

    $recomendacion = reglaRenalDe($this->patient);

    expect($recomendacion)->not->toBeNull();
    expect($recomendacion->priority->value)->toBe('alta');
    expect($recomendacion->reason)->toContain('A3');
});

test('una categoría intermedia sin progresión no dispara', function () {
    controlDeLaboratorio($this->patient, $this->medico, 52, 10, 'G3a');

    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('una caída anualizada por encima del umbral dispara prioridad media', function () {
    // 14 puntos en 300 días equivalen a 17 por año, por encima de los 5 de KDIGO.
    controlDeLaboratorio($this->patient, $this->medico, 78, 300, 'G2');
    controlDeLaboratorio($this->patient, $this->medico, 64, 5, 'G2');

    $recomendacion = reglaRenalDe($this->patient);

    expect($recomendacion)->not->toBeNull();
    expect($recomendacion->priority->value)->toBe('media');
});

test('el motivo muestra las dos cifras, sus fechas y el umbral', function () {
    controlDeLaboratorio($this->patient, $this->medico, 78, 300, 'G2');
    controlDeLaboratorio($this->patient, $this->medico, 64, 5, 'G2');

    $motivo = reglaRenalDe($this->patient)->reason;

    expect($motivo)->toContain('78');
    expect($motivo)->toContain('64');
    // 295 días es el intervalo real entre los dos laboratorios.
    expect($motivo)->toContain('295 días');
    expect($motivo)->toContain('17,3 por año');
    expect($motivo)->toContain('KDIGO');
});

test('una caída por debajo del umbral no dispara', function () {
    // 3 puntos en 400 días son 2,7 por año: por debajo de los 5 de KDIGO.
    controlDeLaboratorio($this->patient, $this->medico, 78, 400, 'G2');
    controlDeLaboratorio($this->patient, $this->medico, 75, 5, 'G2');

    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('una TFGe que mejora no se lee como progresión', function () {
    controlDeLaboratorio($this->patient, $this->medico, 60, 300, 'G3a');
    controlDeLaboratorio($this->patient, $this->medico, 75, 5, 'G2');

    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('un solo control no dispara la rama de progresión', function () {
    controlDeLaboratorio($this->patient, $this->medico, 64, 5, 'G2');

    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('dos laboratorios con la misma fecha no rompen ni disparan', function () {
    controlDeLaboratorio($this->patient, $this->medico, 80, 10, 'G2');
    controlDeLaboratorio($this->patient, $this->medico, 50, 10, 'G3a');

    // Sin división por cero y sin alarma: no hay intervalo que anualizar.
    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('dos laboratorios demasiado próximos tampoco disparan', function () {
    // Una caída grande en 10 días es más probable que sea variabilidad de
    // medición que progresión real, así que no se anualiza.
    controlDeLaboratorio($this->patient, $this->medico, 80, 15, 'G2');
    controlDeLaboratorio($this->patient, $this->medico, 55, 5, 'G3a');

    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('un paciente sin controles renales no genera esta recomendación', function () {
    expect(reglaRenalDe($this->patient))->toBeNull();
});

test('la regla está registrada en el motor de apoyo a decisiones', function () {
    controlDeLaboratorio($this->patient, $this->medico, 20, 10, 'G4');

    $claves = recomendacionesDe($this->patient)->pluck('ruleKey')->all();

    expect($claves)->toContain((new KidneyFunctionDecline)->key());
});

test('la categoría avanzada tiene prioridad sobre la rama de progresión', function () {
    // Con ambas condiciones presentes, se informa la más urgente una sola vez.
    controlDeLaboratorio($this->patient, $this->medico, 60, 300, 'G3a');
    controlDeLaboratorio($this->patient, $this->medico, 20, 5, 'G4');

    $renales = recomendacionesDe($this->patient)->where('ruleKey', 'deterioro_funcion_renal');

    expect($renales)->toHaveCount(1);
    expect($renales->first()->priority->value)->toBe('alta');
});
