<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

test('cerrar una teleconsulta guarda las notas y completa la cita', function () {
    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $response = $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $appointment), [
        'notes' => 'Paciente refiere mejoría. Se ajusta dosis y se cita en 30 días.',
    ]);

    $response->assertRedirect(route('medico.citas.index'));

    $teleconsultation = Teleconsultation::where('appointment_id', $appointment->id)->first();
    expect($teleconsultation->status)->toBe(Teleconsultation::STATUS_FINISHED);
    expect($teleconsultation->notes)->toContain('mejoría');

    // Cerrar la sala y completar la cita son el mismo hecho asistencial.
    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

test('no se puede cerrar una teleconsulta sin notas clínicas', function () {
    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $appointment), ['notes' => ''])
        ->assertSessionHasErrors('notes');

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
});

test('un paciente no puede cerrar una teleconsulta', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $appointment = Appointment::factory()->create([
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);

    $this->actingAs($paciente)
        ->post(route('medico.citas.teleconsulta.complete', $appointment), ['notes' => 'Intento no autorizado'])
        ->assertForbidden();
});
