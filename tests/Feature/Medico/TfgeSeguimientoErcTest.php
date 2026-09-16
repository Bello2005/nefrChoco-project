<?php

use App\Enums\BiologicalSex;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Persistencia del resultado renal junto al formulario.
 *
 * La TFGe y las categorías KDIGO se congelan al guardar: si la ecuación cambia
 * —CKD-EPI ya cambió en 2021— el histórico debe seguir mostrando lo que se
 * concluyó ese día.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function aplicarSeguimientoRenal($test, Patient $patient, array $respuestas = [])
{
    return $test->actingAs($test->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $patient->id,
        'form_type' => 'seguimiento_erc',
        'answers' => array_merge([
            'creatinina' => 0.80,
            'fecha_laboratorio' => now()->subDays(5)->format('Y-m-d'),
            'relacion_albumina_creatinina' => 45,
        ], $respuestas),
    ]);
}

test('guardar el seguimiento calcula y persiste la TFGe y sus categorías', function () {
    // Mujer de 50 años con creatinina 0.80: la calculadora de la NKF da 90.
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subDays(5)->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient);

    $formulario = ClinicalForm::first();

    expect($formulario->egfr)->toBe(90);
    expect($formulario->kdigo_g)->toBe('G1');
    expect($formulario->kdigo_a)->toBe('A2');
});

test('sin examen de albuminuria la categoría A queda vacía', function () {
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subDays(5)->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient, ['relacion_albumina_creatinina' => null]);

    $formulario = ClinicalForm::first();

    expect($formulario->egfr)->toBe(90);
    expect($formulario->kdigo_a)->toBeNull();
});

test('la edad se toma a la fecha del laboratorio y no a la de captura', function () {
    $fechaLaboratorio = now()->subYears(2);

    // Cumple 50 años a la fecha del laboratorio, pero 52 al día de hoy.
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => $fechaLaboratorio->copy()->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient, [
        'creatinina' => 0.80,
        'fecha_laboratorio' => $fechaLaboratorio->format('Y-m-d'),
    ]);

    // 90 corresponde a 50 años (valor verificado con la NKF). Con 52 años el
    // resultado sería menor, así que este número distingue las dos lecturas.
    expect(ClinicalForm::first()->egfr)->toBe(90);
});

test('el resultado queda congelado y no se reescribe si cambian los cortes', function () {
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subDays(5)->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient);
    expect(ClinicalForm::first()->kdigo_g)->toBe('G1');

    // Alguien cambia la tabla de categorías más adelante.
    config(['clinical_support.kidney.gfr_categories' => [
        ['category' => 'G9', 'min' => 0],
    ]]);

    // Lo guardado no cambia: es el resultado de aquel día, no el de hoy.
    expect(ClinicalForm::first()->fresh()->kdigo_g)->toBe('G1');
});

test('una creatinina alta clasifica en una categoría avanzada', function () {
    // Mujer de 50 años con creatinina 2.20: la NKF da 27, que es G4.
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subDays(5)->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient, [
        'creatinina' => 2.20,
        'relacion_albumina_creatinina' => 800,
    ]);

    $formulario = ClinicalForm::first();

    expect($formulario->egfr)->toBe(27);
    expect($formulario->kdigo_g)->toBe('G4');
    expect($formulario->kdigo_a)->toBe('A3');
});

test('los demás instrumentos no calculan TFGe', function () {
    $patient = Patient::factory()->create();

    $this->actingAs($this->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $patient->id,
        'form_type' => 'adherencia_tratamiento',
        'answers' => [
            'olvida_medicamento' => 'no',
            'toma_hora_indicada' => 'si',
            'suspende_si_mejora' => 'no',
            'suspende_si_mal' => 'no',
            'barreras' => null,
        ],
    ]);

    $formulario = ClinicalForm::first();

    expect($formulario->egfr)->toBeNull();
    expect($formulario->kdigo_g)->toBeNull();
    // Este sí puntúa, a diferencia del renal.
    expect($formulario->risk_level)->toBe('adherente');
});

test('la ficha del paciente expone la categoría KDIGO del seguimiento', function () {
    $patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subDays(5)->subYears(50)->format('Y-m-d'),
    ]);

    aplicarSeguimientoRenal($this, $patient);

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('clinicalForms.0.kdigoG', 'G1')
            ->where('clinicalForms.0.egfr', 90)
            // Sin puntuación: la insignia debe pintarse con la categoría.
            ->where('clinicalForms.0.riskLevel', null)
        );
});
