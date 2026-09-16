<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

/**
 * Consentimiento informado de teleconsulta (Resolución 2654 de 2019).
 *
 * Es independiente del consentimiento de datos de la Ley 1581: aceptar que
 * traten tu información no equivale a aceptar que te atiendan sin examen
 * físico y con una conexión que puede cortarse.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function citaConSala(User $paciente, User $medico, Patient $patient): Appointment
{
    return Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $medico->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ]);
}

test('sin autorizar la modalidad, la sala envía primero al consentimiento', function () {
    $patient = Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $cita))
        ->assertRedirect(route('paciente.mis-citas.teleconsulta.consentimiento', $cita));
});

test('la pantalla de consentimiento se muestra con la cita a la vista', function () {
    $patient = Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta.consentimiento', $cita))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('paciente/teleconsulta/consentimiento')
            ->where('appointment.id', $cita->id)
            ->where('version', config('privacy.teleconsultation_consent_version'))
        );
});

test('al autorizar queda la fecha y la versión, y entra a la sala', function () {
    $patient = Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->post(route('paciente.mis-citas.teleconsulta.consentimiento.store', $cita), ['accepted' => true])
        ->assertRedirect(route('paciente.mis-citas.teleconsulta', $cita));

    $patient->refresh();

    expect($patient->teleconsultation_consent_accepted_at)->not->toBeNull();
    expect($patient->teleconsultation_consent_version)->toBe(config('privacy.teleconsultation_consent_version'));
    expect($patient->hasCurrentTeleconsultationConsent())->toBeTrue();
});

test('sin marcar la casilla no se registra la autorización', function () {
    $patient = Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->post(route('paciente.mis-citas.teleconsulta.consentimiento.store', $cita), ['accepted' => false])
        ->assertSessionHasErrors('accepted');

    expect($patient->fresh()->teleconsultation_consent_accepted_at)->toBeNull();
});

test('con la autorización vigente entra directo a la sala', function () {
    $patient = Patient::factory()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $cita))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('paciente/teleconsulta/show'));
});

test('quien ya autorizó no vuelve a ver la pantalla', function () {
    $patient = Patient::factory()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta.consentimiento', $cita))
        ->assertRedirect(route('paciente.mis-citas.teleconsulta', $cita));
});

test('si cambia la versión del texto se vuelve a pedir la autorización', function () {
    $patient = Patient::factory()->create([
        'user_id' => $this->user->id,
        'teleconsultation_consent_accepted_at' => now()->subYear(),
        'teleconsultation_consent_version' => '2024-01',
    ]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    config(['privacy.teleconsultation_consent_version' => '2026-01']);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $cita))
        ->assertRedirect(route('paciente.mis-citas.teleconsulta.consentimiento', $cita));
});

test('un paciente no puede autorizar sobre la cita de otro', function () {
    Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);

    $otroUsuario = User::factory()->create();
    $otroUsuario->assignRole('paciente');
    $otroPaciente = Patient::factory()->create(['user_id' => $otroUsuario->id]);
    $citaAjena = citaConSala($otroUsuario, $this->medico, $otroPaciente);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta.consentimiento', $citaAjena))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->post(route('paciente.mis-citas.teleconsulta.consentimiento.store', $citaAjena), ['accepted' => true])
        ->assertForbidden();
});

test('la autorización de teleconsulta queda en la auditoría', function () {
    $patient = Patient::factory()->withoutTeleconsultationConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->post(route('paciente.mis-citas.teleconsulta.consentimiento.store', $cita), ['accepted' => true]);

    $activity = Activity::where('log_name', 'consentimiento')
        ->where('event', 'teleconsulta_autorizada')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($this->user->id);
    expect($activity->properties['version'])->toBe(config('privacy.teleconsultation_consent_version'));
    expect($activity->properties['cita'])->toBe($cita->id);
});

test('autorizar la teleconsulta no sustituye al consentimiento de datos', function () {
    // Sin el consentimiento de la Ley 1581 el muro general sigue actuando,
    // aunque la modalidad de videollamada ya esté autorizada.
    $patient = Patient::factory()->withoutConsent()->create(['user_id' => $this->user->id]);
    $cita = citaConSala($this->user, $this->medico, $patient);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $cita))
        ->assertRedirect(route('paciente.consentimiento.show'));
});
