<?php

use App\Enums\EcntCategory;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicalFormCatalog;
use Database\Seeders\RoleSeeder;

/**
 * Instrumento de seguimiento de enfermedad renal crónica.
 *
 * La IPS es especializada en salud renal y el catálogo no tenía nada de ERC.
 * A diferencia del tamizaje de diabetes, este instrumento no suma puntos: su
 * resultado es la TFG estimada y su categoría KDIGO.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
});

function seguimientoRenal(array $respuestas = []): array
{
    return array_merge([
        'creatinina' => 1.4,
        'fecha_laboratorio' => now()->subDays(3)->format('Y-m-d'),
        'relacion_albumina_creatinina' => 45,
        'sintomas' => 'Refiere cansancio al final del día.',
        'plan' => 'Continuar esquema y repetir laboratorio en tres meses.',
    ], $respuestas);
}

test('el catálogo expone el instrumento renal con su categoría y sin puntuación', function () {
    $plantilla = ClinicalFormCatalog::find('seguimiento_erc');

    expect($plantilla)->not->toBeNull();
    expect($plantilla['category'])->toBe(EcntCategory::ChronicKidneyDisease->value);
    expect($plantilla['scoring']['enabled'])->toBeFalse();
    expect(ClinicalFormCatalog::score('seguimiento_erc', seguimientoRenal()))->toBeNull();
});

test('las reglas de validación derivan el tipo fecha del propio catálogo', function () {
    $reglas = ClinicalFormCatalog::validationRules('seguimiento_erc');

    expect($reglas['answers.fecha_laboratorio'])->toContain('date');
    expect($reglas['answers.fecha_laboratorio'])->toContain('before_or_equal:today');
    expect($reglas['answers.creatinina'])->toContain('required');
    // El examen de albuminuria no siempre está disponible en zona rural.
    expect($reglas['answers.relacion_albumina_creatinina'])->toContain('nullable');
});

test('aplicar el seguimiento renal guarda el formulario', function () {
    $this->actingAs($this->medico)->post(route('medico.formularios-clinicos.store'), [
        'patient_id' => $this->patient->id,
        'form_type' => 'seguimiento_erc',
        'answers' => seguimientoRenal(),
    ]);

    $formulario = ClinicalForm::first();

    expect($formulario)->not->toBeNull();
    expect($formulario->form_type)->toBe('seguimiento_erc');
    expect($formulario->answers['creatinina'])->toBe(1.4);
    // Sin puntuación: este instrumento no suma respuestas.
    expect($formulario->score)->toBeNull();
});

test('una fecha de laboratorio futura es rechazada', function () {
    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $this->patient->id,
            'form_type' => 'seguimiento_erc',
            'answers' => seguimientoRenal(['fecha_laboratorio' => now()->addWeek()->format('Y-m-d')]),
        ])
        ->assertSessionHasErrors('answers.fecha_laboratorio');

    expect(ClinicalForm::count())->toBe(0);
});

test('la creatinina es obligatoria y la albuminuria no', function () {
    $sinCreatinina = seguimientoRenal();
    unset($sinCreatinina['creatinina']);

    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $this->patient->id,
            'form_type' => 'seguimiento_erc',
            'answers' => $sinCreatinina,
        ])
        ->assertSessionHasErrors('answers.creatinina');

    $sinAlbuminuria = seguimientoRenal();
    unset($sinAlbuminuria['relacion_albumina_creatinina']);

    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $this->patient->id,
            'form_type' => 'seguimiento_erc',
            'answers' => $sinAlbuminuria,
        ])
        ->assertSessionHasNoErrors();

    expect(ClinicalForm::count())->toBe(1);
});

test('no se puede aplicar a una ficha sin sexo biológico', function () {
    $sinSexo = Patient::factory()->withoutBiologicalSex()->create();

    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $sinSexo->id,
            'form_type' => 'seguimiento_erc',
            'answers' => seguimientoRenal(),
        ])
        ->assertSessionHasErrors('patient_id');

    expect(ClinicalForm::count())->toBe(0);

    $mensaje = session('errors')->first('patient_id');
    expect($mensaje)->toContain('sexo biológico');
    expect($mensaje)->toContain('ficha del paciente');
});

test('el bloqueo por sexo faltante no afecta a los demás instrumentos', function () {
    $sinSexo = Patient::factory()->withoutBiologicalSex()->create();

    // El tamizaje de diabetes no usa el sexo, así que debe seguir aplicándose.
    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $sinSexo->id,
            'form_type' => 'tamizaje_diabetes',
            'answers' => [
                'edad' => '45_54',
                'imc' => 'normal',
                'perimetro_abdominal' => 'normal',
                'actividad_fisica' => 'si',
                'frutas_verduras' => 'diario',
                'antecedente_familiar' => 'no',
                'observaciones' => null,
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(ClinicalForm::count())->toBe(1);
});
