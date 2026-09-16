<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;

/**
 * Matriz de autorización entre profesionales.
 *
 * El padrón de pacientes es institucional (cualquier médico de la IPS atiende
 * a cualquier persona inscrita), pero la agenda y las teleconsultas pertenecen
 * al profesional que las atiende. Cada caso de aquí corresponde a un acceso que
 * la auditoría encontró abierto y que se verificó consumando la escritura.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medicoA = User::factory()->create(['name' => 'Médica A']);
    $this->medicoA->assignRole('medico');

    $this->medicoB = User::factory()->create(['name' => 'Médico B']);
    $this->medicoB->assignRole('medico');

    $this->patient = Patient::factory()->create();
});

function citaDe(User $doctor, Patient $patient, array $extra = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now()->addDay(),
    ], $extra));
}

test('un médico no puede abrir el formulario de edición de la cita de otro', function () {
    $cita = citaDe($this->medicoB, $this->patient);

    $this->actingAs($this->medicoA)
        ->get(route('medico.citas.edit', $cita))
        ->assertForbidden();
});

test('un médico no puede reprogramar ni cancelar la cita de otro', function () {
    $cita = citaDe($this->medicoB, $this->patient);

    $this->actingAs($this->medicoA)
        ->put(route('medico.citas.update', $cita), [
            'patient_id' => $this->patient->id,
            'scheduled_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
            'type' => Appointment::TYPE_IN_PERSON,
            'status' => Appointment::STATUS_CANCELLED,
        ])
        ->assertForbidden();

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
    expect($cita->fresh()->type)->toBe(Appointment::TYPE_TELECONSULTATION);
});

test('un médico no puede eliminar la cita de otro', function () {
    $cita = citaDe($this->medicoB, $this->patient);

    $this->actingAs($this->medicoA)
        ->delete(route('medico.citas.destroy', $cita))
        ->assertForbidden();

    expect(Appointment::find($cita->id))->not->toBeNull();
});

test('un médico no puede abrir la sala de teleconsulta de otro', function () {
    $cita = citaDe($this->medicoB, $this->patient);

    $this->actingAs($this->medicoA)
        ->get(route('medico.citas.teleconsulta', $cita))
        ->assertForbidden();
});

test('un médico no puede firmar notas clínicas en la teleconsulta de otro', function () {
    $cita = citaDe($this->medicoB, $this->patient);

    $this->actingAs($this->medicoA)
        ->post(route('medico.citas.teleconsulta.complete', $cita), [
            'notes' => 'Nota escrita por un profesional que no atiende esta cita.',
        ])
        ->assertForbidden();

    expect(Teleconsultation::where('appointment_id', $cita->id)->value('notes'))->toBeNull();
    expect($cita->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
});

test('el médico que atiende la cita sí puede gestionarla y cerrarla', function () {
    $cita = citaDe($this->medicoA, $this->patient);

    $this->actingAs($this->medicoA)->get(route('medico.citas.edit', $cita))->assertOk();
    $this->actingAs($this->medicoA)->get(route('medico.citas.teleconsulta', $cita))->assertOk();

    $this->actingAs($this->medicoA)
        ->post(route('medico.citas.teleconsulta.complete', $cita), ['notes' => 'Control sin novedades.'])
        ->assertRedirect(route('medico.citas.index'));

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

test('el padrón es institucional: cualquier médico consulta y edita cualquier ficha', function () {
    // Paciente sin ninguna relación con la médica A.
    $ajeno = Patient::factory()->create();
    citaDe($this->medicoB, $ajeno);

    $this->actingAs($this->medicoA)->get(route('medico.pacientes.show', $ajeno))->assertOk();
    $this->actingAs($this->medicoA)->get(route('medico.pacientes.edit', $ajeno))->assertOk();
});

test('el rol médico ya no expone ninguna ruta para eliminar pacientes', function () {
    expect(Route::has('medico.pacientes.destroy'))->toBeFalse();
});

test('un médico no puede eliminar pacientes desde la zona de administración', function () {
    $this->actingAs($this->medicoA)
        ->delete(route('admin.pacientes.destroy', $this->patient))
        ->assertForbidden();

    expect($this->patient->fresh()->deleted_at)->toBeNull();
});

test('un administrador sí puede eliminar la ficha de un paciente', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('admin.pacientes.destroy', $this->patient))
        ->assertRedirect(route('admin.pacientes.index'));

    expect($this->patient->fresh()->deleted_at)->not->toBeNull();
});

test('la custodia del padrón muestra cuánto dato clínico cuelga de cada ficha', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    citaDe($this->medicoB, $this->patient);

    $this->actingAs($admin)
        ->get(route('admin.pacientes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pacientes/index')
            ->where('patients.0.appointments', 1)
        );
});

test('un paciente no alcanza la custodia del padrón', function () {
    $user = User::factory()->create();
    $user->assignRole('paciente');
    Patient::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('admin.pacientes.index'))->assertForbidden();
});
