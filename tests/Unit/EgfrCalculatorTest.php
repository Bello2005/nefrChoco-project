<?php

use App\Enums\BiologicalSex;
use App\Services\EgfrCalculator;
use Tests\TestCase;

// Las categorías KDIGO viven en config, así que hace falta la aplicación.
uses(TestCase::class);

/**
 * CKD-EPI 2021 sin coeficiente de raza.
 *
 * Los valores esperados NO salen de cálculo propio: se leyeron de la
 * calculadora oficial de la National Kidney Foundation
 * (kidney.org/professionals/kdoqi/gfr_calculator), que implementa esta misma
 * ecuación, introduciendo cada combinación de creatinina, edad y sexo.
 */
dataset('valores verificados con la calculadora de la NKF', [
    // [creatinina mg/dL, edad, sexo, TFGe que reporta la NKF]
    'mujer 55 años, 1.20' => [1.20, 55, BiologicalSex::Female, 53],
    'hombre 60 años, 1.50' => [1.50, 60, BiologicalSex::Male, 53],
    'mujer 45 años, 0.80' => [0.80, 45, BiologicalSex::Female, 93],
    'hombre 30 años, 1.00' => [1.00, 30, BiologicalSex::Male, 104],
    'mujer 70 años, 2.50' => [2.50, 70, BiologicalSex::Female, 20],
    'hombre 75 años, 3.20' => [3.20, 75, BiologicalSex::Male, 19],
    'mujer 50 años, 0.79' => [0.79, 50, BiologicalSex::Female, 91],
    'mujer 50 años, 0.80' => [0.80, 50, BiologicalSex::Female, 90],
    'hombre 50 años, 1.00' => [1.00, 50, BiologicalSex::Male, 92],
    'hombre 50 años, 1.02' => [1.02, 50, BiologicalSex::Male, 90],
    'hombre 50 años, 1.03' => [1.03, 50, BiologicalSex::Male, 88],
    'mujer 50 años, 1.15' => [1.15, 50, BiologicalSex::Female, 58],
    'mujer 50 años, 1.16' => [1.16, 50, BiologicalSex::Female, 57],
    'mujer 50 años, 1.60' => [1.60, 50, BiologicalSex::Female, 39],
    'mujer 50 años, 2.20' => [2.20, 50, BiologicalSex::Female, 27],
    'mujer 50 años, 4.00' => [4.00, 50, BiologicalSex::Female, 13],
    'mujer 50 años, 4.30' => [4.30, 50, BiologicalSex::Female, 12],
    'hombre 60 años, 1.40' => [1.40, 60, BiologicalSex::Male, 58],
]);

test('la TFGe coincide con la calculadora oficial de la NKF', function (float $creatinina, int $edad, BiologicalSex $sexo, int $esperado) {
    expect(app(EgfrCalculator::class)->estimate($creatinina, $edad, $sexo))->toBe($esperado);
})->with('valores verificados con la calculadora de la NKF');

test('el sexo cambia el resultado con la misma creatinina y edad', function () {
    $calculadora = app(EgfrCalculator::class);

    $mujer = $calculadora->estimate(1.00, 50, BiologicalSex::Female);
    $hombre = $calculadora->estimate(1.00, 50, BiologicalSex::Male);

    expect($mujer)->not->toBe($hombre);
});

test('la TFGe baja al aumentar la edad con la misma creatinina', function () {
    $calculadora = app(EgfrCalculator::class);

    expect($calculadora->estimate(1.00, 70, BiologicalSex::Male))
        ->toBeLessThan($calculadora->estimate(1.00, 40, BiologicalSex::Male));
});

dataset('bordes de categoría G de KDIGO', [
    'G1 justo en 90' => [90, 'G1'],
    'G2 justo en 89' => [89, 'G2'],
    'G2 justo en 60' => [60, 'G2'],
    'G3a justo en 59' => [59, 'G3a'],
    'G3a justo en 45' => [45, 'G3a'],
    'G3b justo en 44' => [44, 'G3b'],
    'G3b justo en 30' => [30, 'G3b'],
    'G4 justo en 29' => [29, 'G4'],
    'G4 justo en 15' => [15, 'G4'],
    'G5 justo en 14' => [14, 'G5'],
    'G5 en cero' => [0, 'G5'],
    'G1 muy alta' => [140, 'G1'],
]);

test('la categoría G corresponde a la tabla de KDIGO', function (int $egfr, string $esperada) {
    expect(app(EgfrCalculator::class)->gCategory($egfr))->toBe($esperada);
})->with('bordes de categoría G de KDIGO');

dataset('bordes de categoría A de KDIGO', [
    'A1 por debajo de 30' => [29.9, 'A1'],
    'A1 en cero' => [0.0, 'A1'],
    'A2 justo en 30' => [30.0, 'A2'],
    'A2 justo en 300' => [300.0, 'A2'],
    'A3 por encima de 300' => [300.1, 'A3'],
    'A3 nefrótica' => [2500.0, 'A3'],
]);

test('la categoría A corresponde a la tabla de KDIGO', function (float $acr, string $esperada) {
    expect(app(EgfrCalculator::class)->aCategory($acr))->toBe($esperada);
})->with('bordes de categoría A de KDIGO');

test('sin dato de albuminuria no hay categoría A, y eso no es lo mismo que A1', function () {
    // En zona rural el examen no siempre está disponible: ausencia de dato no
    // puede leerse como albuminuria normal.
    expect(app(EgfrCalculator::class)->aCategory(null))->toBeNull();
});
