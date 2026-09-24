<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * "Probar mi conexión" (Res. 1644 de 2026, art. 24.8): el paciente mide su
 * conexión antes de la cita y el servidor guarda solo el nivel y la fecha,
 * que ve el médico de la cita y nadie más.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->pacienteUser = User::factory()->create();
    $this->pacienteUser->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->pacienteUser->id]);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->cita = Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'doctor_id' => $this->medico->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ]);
});

test('el paciente de la cita ve la prueba con los umbrales de la configuración', function () {
    $this->actingAs($this->pacienteUser)
        ->get(route('paciente.mis-citas.probar-conexion', $this->cita))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paciente/teleconsulta/probar-conexion')
            ->where('thresholds.video.min_download_kbps', config('teleconsultation.connection_check.video.min_download_kbps'))
        );
});

test('se guarda solo el nivel y la fecha', function () {
    $this->actingAs($this->pacienteUser)
        ->post(route('paciente.mis-citas.probar-conexion.store', $this->cita), ['level' => 'solo_audio'])
        ->assertSessionHasNoErrors();

    $cita = $this->cita->fresh();
    expect($cita->connection_check_level)->toBe('solo_audio');
    expect($cita->connection_check_at)->not->toBeNull();
});

test('no se acepta un nivel inventado', function () {
    $this->actingAs($this->pacienteUser)
        ->post(route('paciente.mis-citas.probar-conexion.store', $this->cita), ['level' => 'excelente'])
        ->assertSessionHasErrors('level');

    expect($this->cita->fresh()->connection_check_level)->toBeNull();
});

test('solo el paciente de la cita puede guardar el resultado', function () {
    $otroUser = User::factory()->create();
    $otroUser->assignRole('paciente');
    Patient::factory()->create(['user_id' => $otroUser->id]);

    $this->actingAs($otroUser)
        ->post(route('paciente.mis-citas.probar-conexion.store', $this->cita), ['level' => 'video'])
        ->assertForbidden();

    $this->actingAs($this->medico)
        ->post(route('paciente.mis-citas.probar-conexion.store', $this->cita), ['level' => 'video'])
        ->assertForbidden();

    expect($this->cita->fresh()->connection_check_level)->toBeNull();
});

test('una cita presencial no tiene prueba de conexión', function () {
    $presencial = Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'doctor_id' => $this->medico->id,
        'type' => Appointment::TYPE_IN_PERSON,
    ]);

    $this->actingAs($this->pacienteUser)
        ->get(route('paciente.mis-citas.probar-conexion', $presencial))
        ->assertRedirect(route('paciente.mis-citas.index'));
});

test('el médico de la cita ve el resultado en su agenda y en la sala, y otro médico no', function () {
    $this->cita->forceFill(['connection_check_level' => 'insuficiente', 'connection_check_at' => now()])->save();

    $this->actingAs($this->medico)
        ->get(route('medico.citas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('appointments.0.connection_check.level', 'insuficiente'));

    $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $this->cita))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('connectionCheck.level', 'insuficiente'));

    $otro = User::factory()->create();
    $otro->assignRole('medico');

    $this->actingAs($otro)
        ->get(route('medico.citas.index'))
        ->assertInertia(fn (Assert $page) => $page->where('appointments', []));

    $this->actingAs($otro)
        ->get(route('medico.citas.teleconsulta', $this->cita))
        ->assertForbidden();
});
