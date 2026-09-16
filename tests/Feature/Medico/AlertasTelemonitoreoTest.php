<?php

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\VitalSignService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
});

test('una alerta antigua no desaparece bajo mediciones normales más recientes', function () {
    $alarmante = VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::OxygenSaturation->value,
        'value' => 84,
        'unit' => '%',
        'recorded_at' => now()->subDays(30),
    ]);

    // Antes el panel traía las 80 mediciones más recientes y filtraba en PHP,
    // así que una tanda de controles normales tapaba la alerta de verdad.
    VitalSign::factory()->count(120)->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::HeartRate->value,
        'value' => 72,
        'unit' => 'lpm',
        'recorded_at' => now()->subDay(),
    ]);

    $alertas = app(VitalSignService::class)->outOfRangeAlerts();

    expect($alertas->pluck('id')->all())->toContain($alarmante->id);
});

test('las mediciones dentro de rango no aparecen como alerta', function () {
    VitalSign::factory()->count(10)->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::HeartRate->value,
        'value' => 72,
        'unit' => 'lpm',
    ]);

    expect(app(VitalSignService::class)->outOfRangeAlerts())->toBeEmpty();
});

test('detecta tanto los valores altos como los bajos de cada tipo', function () {
    $alta = VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::Glucose->value,
        'value' => 260,
        'unit' => 'mg/dL',
    ]);

    $baja = VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::OxygenSaturation->value,
        'value' => 80,
        'unit' => '%',
    ]);

    $ids = app(VitalSignService::class)->outOfRangeAlerts()->pluck('id')->all();

    expect($ids)->toContain($alta->id);
    expect($ids)->toContain($baja->id);
});

test('el panel del médico respeta el límite de alertas que se le piden', function () {
    VitalSign::factory()->count(12)->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::Glucose->value,
        'value' => 240,
        'unit' => 'mg/dL',
    ]);

    expect(app(VitalSignService::class)->outOfRangeAlerts(5))->toHaveCount(5);
});
