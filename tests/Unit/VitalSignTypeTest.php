<?php

use App\Enums\VitalSignType;

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
    expect(VitalSignType::Weight->unit())->toBe('kg');
    expect(VitalSignType::Temperature->unit())->toBe('°C');
});
