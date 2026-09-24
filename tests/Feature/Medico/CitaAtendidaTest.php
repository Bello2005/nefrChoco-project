<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
    $this->otroPaciente = Patient::factory()->create();
});

function citaDelMedico(User $medico, Patient $patient, array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}

function datosDeEdicion(Patient $patient, string $status): array
{
    return [
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'type' => Appointment::TYPE_IN_PERSON,
        'status' => $status,
    ];
}

test('una cita completada no se puede editar ni cambiar de paciente o de estado', function () {
    $cita = citaDelMedico($this->medico, $this->patient, ['status' => Appointment::STATUS_COMPLETED]);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.edit', $cita))
        ->assertForbidden();

    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), datosDeEdicion($this->otroPaciente, Appointment::STATUS_SCHEDULED))
        ->assertForbidden();

    $cita->refresh();
    expect($cita->patient_id)->toBe($this->patient->id);
    expect($cita->status)->toBe(Appointment::STATUS_COMPLETED);
});

test('una cita con la teleconsulta finalizada queda fija aunque su estado diga programada', function () {
    $cita = citaDelMedico($this->medico, $this->patient);
    Teleconsultation::factory()->create([
        'appointment_id' => $cita->id,
        'status' => Teleconsultation::STATUS_FINISHED,
        'notes' => 'Nota de una consulta que ya ocurrió.',
    ]);

    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), datosDeEdicion($this->otroPaciente, Appointment::STATUS_CANCELLED))
        ->assertForbidden();

    expect($cita->fresh()->patient_id)->toBe($this->patient->id);
});

test('una cita programada se sigue editando y cancelando', function () {
    $cita = citaDelMedico($this->medico, $this->patient, ['type' => Appointment::TYPE_IN_PERSON]);

    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), datosDeEdicion($this->patient, Appointment::STATUS_CANCELLED))
        ->assertRedirect(route('medico.citas.index'));

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_CANCELLED);

    // Cancelada no es final: se puede corregir si fue un error de agenda.
    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), datosDeEdicion($this->patient, Appointment::STATUS_SCHEDULED))
        ->assertRedirect(route('medico.citas.index'));

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
});

test('el listado marca como no editables las citas atendidas', function () {
    $programada = citaDelMedico($this->medico, $this->patient);
    $completada = citaDelMedico($this->medico, $this->patient, ['status' => Appointment::STATUS_COMPLETED]);
    $conSalaCerrada = citaDelMedico($this->medico, $this->patient);
    Teleconsultation::factory()->create([
        'appointment_id' => $conSalaCerrada->id,
        'status' => Teleconsultation::STATUS_FINISHED,
    ]);

    $this->actingAs($this->medico)
        ->get(route('medico.citas.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($programada, $completada, $conSalaCerrada) {
            $porId = collect($page->toArray()['props']['appointments'])->keyBy('id');

            expect($porId[$programada->id]['can_edit'])->toBeTrue();
            expect($porId[$completada->id]['can_edit'])->toBeFalse();
            expect($porId[$conSalaCerrada->id]['can_edit'])->toBeFalse();
        });
});
