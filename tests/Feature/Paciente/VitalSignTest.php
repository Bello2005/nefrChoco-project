<?php

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un paciente puede registrar su propia medición y queda asociada a su ficha', function () {
    $user = User::factory()->create();
    $user->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('paciente.signos-vitales.store'), [
        'type' => VitalSignType::Glucose->value,
        'value' => 155,
        'recorded_at' => now()->subHour()->format('Y-m-d H:i:s'),
        'notes' => 'En ayunas',
    ]);

    $reading = VitalSign::first();

    expect($reading)->not->toBeNull();
    expect($reading->patient_id)->toBe($patient->id);
    expect($reading->recorded_by)->toBe($user->id);
    // La unidad la impone el tipo de signo, no el formulario.
    expect($reading->unit)->toBe('mg/dL');
    expect($reading->isOutOfRange())->toBeTrue();
});

test('un paciente solo ve las series de sus propias mediciones', function () {
    $user = User::factory()->create();
    $user->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $user->id]);
    VitalSign::factory()->create(['patient_id' => $patient->id, 'recorded_by' => $user->id]);

    $otherPatient = Patient::factory()->create();
    VitalSign::factory()->count(3)->create(['patient_id' => $otherPatient->id, 'recorded_by' => $user->id]);

    $response = $this->actingAs($user)->get(route('paciente.signos-vitales.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('paciente/signos-vitales/index')
        ->has('series', 1)
        ->where('series.0.points', fn ($points) => count($points) === 1)
    );
});

test('una medición con fecha futura es rechazada', function () {
    $user = User::factory()->create();
    $user->assignRole('paciente');
    Patient::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post(route('paciente.signos-vitales.store'), [
        'type' => VitalSignType::HeartRate->value,
        'value' => 70,
        'recorded_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ]);

    $response->assertSessionHasErrors('recorded_at');
    expect(VitalSign::count())->toBe(0);
});
