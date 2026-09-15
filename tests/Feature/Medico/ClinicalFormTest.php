<?php

use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

test('un médico puede aplicar un formulario de tamizaje y se guarda el puntaje calculado', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($this->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $patient->id,
        'form_type' => 'tamizaje_diabetes',
        'answers' => [
            'edad' => '55_64',
            'imc' => 'obesidad',
            'perimetro_abdominal' => 'muy_elevado',
            'actividad_fisica' => 'no',
            'frutas_verduras' => 'ocasional',
            'antecedente_familiar' => 'primer_grado',
            'observaciones' => 'Se deriva a valoración prioritaria.',
        ],
    ]);

    $form = ClinicalForm::where('patient_id', $patient->id)->first();

    expect($form)->not->toBeNull();
    expect($form->score)->toBe(18);
    expect($form->risk_level)->toBe('alto');
    expect($form->recorded_by)->toBe($this->medico->id);

    $response->assertRedirect(route('medico.formularios-clinicos.show', $form));
});

test('un formulario con una respuesta inválida es rechazado', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($this->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $patient->id,
        'form_type' => 'tamizaje_diabetes',
        'answers' => [
            'edad' => 'valor_inexistente',
            'imc' => 'normal',
            'perimetro_abdominal' => 'normal',
            'actividad_fisica' => 'si',
            'frutas_verduras' => 'diario',
            'antecedente_familiar' => 'no',
        ],
    ]);

    $response->assertSessionHasErrors('answers.edad');
    expect(ClinicalForm::count())->toBe(0);
});

test('un paciente no puede acceder al módulo de formularios clínicos', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $this->actingAs($paciente)
        ->get(route('medico.formularios-clinicos.index'))
        ->assertForbidden();
});
