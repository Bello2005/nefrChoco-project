<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->user->id]);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function teleconsultaDe(Patient $patient, User $medico, array $attributes = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'patient_id' => $patient->id,
        'doctor_id' => $medico->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ], $attributes));
}

test('el paciente entra a la sala de su propia teleconsulta', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('paciente/teleconsulta/show')
            ->where('appointment.id', $appointment->id)
            ->where('roomName', Teleconsultation::where('appointment_id', $appointment->id)->value('room_name'))
        );
});

test('paciente y médico caen en la misma sala', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico);

    $delMedico = $this->actingAs($this->medico)
        ->get(route('medico.citas.teleconsulta', $appointment))
        ->viewData('page')['props']['teleconsultation']['room_name'];

    $delPaciente = $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->viewData('page')['props']['roomName'];

    expect($delPaciente)->toBe($delMedico);
});

test('la pantalla del paciente no expone las notas clínicas de la teleconsulta', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico);

    $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $appointment), [
        'notes' => 'Hallazgo clínico reservado al profesional.',
    ]);

    // Ya cerrada, el paciente ni siquiera llega a la sala; y si llegara, la
    // página nunca recibe las notas como prop.
    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertRedirect(route('paciente.mis-citas.index'));
});

test('un paciente recibe 403 al intentar entrar a la teleconsulta de otro', function () {
    $otroUsuario = User::factory()->create();
    $otroUsuario->assignRole('paciente');
    $otroPaciente = Patient::factory()->create(['user_id' => $otroUsuario->id]);

    $appointment = teleconsultaDe($otroPaciente, $this->medico);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertForbidden();
});

test('una cita presencial no abre sala de videollamada', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico, ['type' => Appointment::TYPE_IN_PERSON]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertRedirect(route('paciente.mis-citas.index'))
        ->assertSessionHas('error');
});

test('no se puede entrar a una teleconsulta cancelada, completada o no asistida', function (string $status) {
    $appointment = teleconsultaDe($this->patient, $this->medico, ['status' => $status]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertRedirect(route('paciente.mis-citas.index'))
        ->assertSessionHas('error');
})->with([
    Appointment::STATUS_CANCELLED,
    Appointment::STATUS_COMPLETED,
    Appointment::STATUS_NO_SHOW,
]);

test('la sala todavía no está disponible demasiado antes de la hora agendada', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico, ['scheduled_at' => now()->addHours(3)]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertRedirect(route('paciente.mis-citas.index'))
        ->assertSessionHas('error');
});

test('la sala ya no está disponible mucho después de la hora agendada', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico, ['scheduled_at' => now()->subHours(4)]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertRedirect(route('paciente.mis-citas.index'))
        ->assertSessionHas('error');
});

test('la ventana de entrada se puede ajustar por configuración', function () {
    config(['teleconsultation.join_window.minutes_before' => 240]);

    $appointment = teleconsultaDe($this->patient, $this->medico, ['scheduled_at' => now()->addHours(3)]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $appointment))
        ->assertOk();
});

test('el paciente no puede cerrar la teleconsulta ni dejar notas clínicas', function () {
    $appointment = teleconsultaDe($this->patient, $this->medico);

    $this->actingAs($this->user)
        ->post(route('medico.citas.teleconsulta.complete', $appointment), ['notes' => 'Intento del paciente'])
        ->assertForbidden();

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
});

test('el listado de citas marca cuáles se pueden abrir ahora', function () {
    $abierta = teleconsultaDe($this->patient, $this->medico);
    $lejana = teleconsultaDe($this->patient, $this->medico, ['scheduled_at' => now()->addDays(3)]);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.index'))
        ->assertInertia(fn ($page) => $page
            ->component('paciente/mis-citas/index')
            ->where('appointments', fn ($appointments) => collect($appointments)
                ->pluck('can_join', 'id')
                ->all() === [$abierta->id => true, $lejana->id => false]
            )
        );
});

test('el dashboard ofrece la sala cuando la teleconsulta ya empezó', function () {
    // Ya pasó la hora, así que dejó de ser "la próxima cita": es justo cuando
    // el paciente necesita el acceso más a la mano.
    $appointment = teleconsultaDe($this->patient, $this->medico, ['scheduled_at' => now()->subMinutes(10)]);

    $this->actingAs($this->user)
        ->get(route('paciente.dashboard'))
        ->assertInertia(fn ($page) => $page->where('activeTeleconsultation.id', $appointment->id));
});
