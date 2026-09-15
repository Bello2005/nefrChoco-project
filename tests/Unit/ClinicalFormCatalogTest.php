<?php

use App\Support\ClinicalFormCatalog;

test('el tamizaje de diabetes clasifica como riesgo bajo un perfil saludable', function () {
    $score = ClinicalFormCatalog::score('tamizaje_diabetes', [
        'edad' => 'menor_45',
        'imc' => 'normal',
        'perimetro_abdominal' => 'normal',
        'actividad_fisica' => 'si',
        'frutas_verduras' => 'diario',
        'antecedente_familiar' => 'no',
    ]);

    expect($score['total'])->toBe(0);
    expect($score['level'])->toBe('bajo');
});

test('el tamizaje de diabetes clasifica como riesgo alto un perfil de máximo riesgo', function () {
    $score = ClinicalFormCatalog::score('tamizaje_diabetes', [
        'edad' => 'mayor_64',
        'imc' => 'obesidad',
        'perimetro_abdominal' => 'muy_elevado',
        'actividad_fisica' => 'no',
        'frutas_verduras' => 'ocasional',
        'antecedente_familiar' => 'primer_grado',
    ]);

    expect($score['total'])->toBe(19);
    expect($score['level'])->toBe('alto');
});

test('el test de adherencia detecta un paciente no adherente', function () {
    $score = ClinicalFormCatalog::score('adherencia_tratamiento', [
        'olvida_medicamento' => 'si',
        'toma_hora_indicada' => 'no',
        'suspende_si_mejora' => 'si',
        'suspende_si_mal' => 'si',
    ]);

    expect($score['total'])->toBe(4);
    expect($score['level'])->toBe('no_adherente');
});

test('un instrumento sin puntuación no devuelve resultado calculado', function () {
    $score = ClinicalFormCatalog::score('seguimiento_hipertension', [
        'presion_sistolica' => 130,
        'presion_diastolica' => 80,
        'consumo_sal' => 'bajo',
    ]);

    expect($score)->toBeNull();
});

test('las reglas de validación se derivan de los campos de la plantilla', function () {
    $rules = ClinicalFormCatalog::validationRules('adherencia_tratamiento');

    expect($rules)->toHaveKey('answers.olvida_medicamento');
    expect($rules['answers.olvida_medicamento'])->toContain('required');
    // El campo marcado como opcional no debe exigirse.
    expect($rules['answers.barreras'])->toContain('nullable');
});
