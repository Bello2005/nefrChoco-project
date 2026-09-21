<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->patient = Patient::factory()->create();
});

function teleconsultaDelMedico(User $medico, Patient $patient, array $attributes = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ], $attributes));
}

test('el médico entra a la sala de su teleconsulta dentro de la ventana', function () {
    $appointment = teleconsultaDelMedico($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $appointment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('medico/teleconsulta/show'));
});

test('no se puede entrar a una teleconsulta cancelada o completada', function (string $status) {
    $appointment = teleconsultaDelMedico($this->medico, $this->patient, ['status' => $status]);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $appointment))
        ->assertRedirect(route('medico.citas.index'))
        ->assertSessionHas('error');
})->with([
    Appointment::STATUS_CANCELLED,
    Appointment::STATUS_COMPLETED,
    Appointment::STATUS_NO_SHOW,
]);

test('la sala todavía no está disponible demasiado antes de la hora agendada', function () {
    $appointment = teleconsultaDelMedico($this->medico, $this->patient, ['scheduled_at' => now()->addHours(3)]);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $appointment))
        ->assertRedirect(route('medico.citas.index'))
        ->assertSessionHas('error');
});

test('la sala ya no está disponible mucho después de la hora agendada', function () {
    $appointment = teleconsultaDelMedico($this->medico, $this->patient, ['scheduled_at' => now()->subHours(4)]);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $appointment))
        ->assertRedirect(route('medico.citas.index'))
        ->assertSessionHas('error');
});
