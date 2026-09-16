<?php

use App\Enums\VitalSignType;
use Tests\TestCase;

// Los rangos de referencia viven en config/vital_signs.php, así que este
// archivo necesita la aplicación levantada aunque no toque la base de datos.
uses(TestCase::class);

test('una saturación baja se evalúa como fuera de rango', function () {
    expect(VitalSignType::OxygenSaturation->evaluate(88))->toBe('bajo');
});

test('una glucemia elevada se evalúa como alta', function () {
    expect(VitalSignType::Glucose->evaluate(200))->toBe('alto');
});

test('un valor dentro del rango de referencia se evalúa como normal', function () {
    expect(VitalSignType::HeartRate->evaluate(72))->toBe('normal');
});

test('cada tipo de signo vital declara su unidad de medida', function () {
    expect(VitalSignType::BloodPressure->unit())->toBe('mmHg');
    expect(VitalSignType::BloodPressureDiastolic->unit())->toBe('mmHg');
    expect(VitalSignType::Weight->unit())->toBe('kg');
    expect(VitalSignType::Temperature->unit())->toBe('°C');
});

test('la diastólica se evalúa contra su propio rango, no el de la sistólica', function () {
    // 85 mmHg es normal para una sistólica baja pero alto para una diastólica.
    expect(VitalSignType::BloodPressureDiastolic->evaluate(85))->toBe('alto');
    expect(VitalSignType::BloodPressureDiastolic->evaluate(70))->toBe('normal');
    expect(VitalSignType::BloodPressureDiastolic->evaluate(55))->toBe('bajo');
});

test('los rangos salen de config y no de valores fijos en el enum', function () {
    config(['vital_signs.reference_ranges.glucemia' => ['min' => 80, 'max' => 110]]);

    expect(VitalSignType::Glucose->evaluate(115))->toBe('alto');
    expect(VitalSignType::Glucose->evaluate(85))->toBe('normal');
});
