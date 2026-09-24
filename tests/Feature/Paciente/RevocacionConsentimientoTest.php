<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Res. 1644 de 2026, art. 7: el consentimiento de teleconsulta es revocable,
 * y un cambio en su texto obliga a aceptarlo de nuevo.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->user->id]);

    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->cita = Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'doctor_id' => $medico->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ]);
});

test('quien aceptó la versión anterior del texto debe aceptar de nuevo antes de entrar a la sala', function () {
    // La versión anterior a la Res. 1644 era 2026-01: la vigente ya no puede ser esa.
    expect(config('privacy.teleconsultation_consent_version'))->not->toBe('2026-01');

    $this->patient->update(['teleconsultation_consent_version' => '2026-01']);

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $this->cita))
        ->assertRedirect(route('paciente.mis-citas.teleconsulta.consentimiento', $this->cita));
});

test('retirar el consentimiento bloquea la sala hasta aceptarlo de nuevo', function () {
    $this->actingAs($this->user)
        ->post(route('paciente.mis-consentimientos.teleconsulta.retirar'))
        ->assertRedirect(route('paciente.mis-consentimientos.show'));

    expect($this->patient->fresh()->teleconsultation_consent_revoked_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->get(route('paciente.mis-citas.teleconsulta', $this->cita))
        ->assertRedirect(route('paciente.mis-citas.teleconsulta.consentimiento', $this->cita));

    $this->actingAs($this->user)
        ->post(route('paciente.mis-citas.teleconsulta.consentimiento.store', $this->cita), ['accepted' => true])
        ->assertRedirect(route('paciente.mis-citas.teleconsulta', $this->cita));

    expect($this->patient->fresh()->hasCurrentTeleconsultationConsent())->toBeTrue();
});

test('la auditoría registra el retiro sin guardar valores', function () {
    $this->actingAs($this->user)->post(route('paciente.mis-consentimientos.teleconsulta.retirar'));

    $registro = Activity::where('event', 'teleconsulta_revocada')->sole();

    expect($registro->causer_id)->toBe($this->user->id);
    expect($registro->subject_id)->toBe($this->patient->id);
    expect($registro->properties)->toBeEmpty();

    // El rastro del cambio en la ficha nombra el campo, no la fecha.
    $cambio = Activity::where('subject_type', Patient::class)->where('event', 'updated')->latest('id')->first();
    expect($cambio->properties['campos'])->toContain('teleconsultation_consent_revoked_at');
    $retiro = $this->patient->fresh()->teleconsultation_consent_revoked_at;
    $auditoria = DB::table('activity_log')->pluck('properties')->implode(' ');
    expect($auditoria)->not->toContain($retiro->toDateTimeString());
    expect($auditoria)->not->toContain($retiro->format('Y-m-d\\TH:i'));
});

test('un paciente no puede retirar el consentimiento de otro', function () {
    $otroUser = User::factory()->create();
    $otroUser->assignRole('paciente');
    $otro = Patient::factory()->create(['user_id' => $otroUser->id]);

    // La ruta no recibe identificador: mandar el de otra ficha no cambia nada.
    $this->actingAs($this->user)
        ->post(route('paciente.mis-consentimientos.teleconsulta.retirar'), ['patient_id' => $otro->id]);

    expect($otro->fresh()->teleconsultation_consent_revoked_at)->toBeNull();
    expect($otro->fresh()->hasCurrentTeleconsultationConsent())->toBeTrue();
    expect($this->patient->fresh()->teleconsultation_consent_revoked_at)->not->toBeNull();
});

test('la pantalla de consentimientos muestra el estado y el correo de privacidad', function () {
    $this->actingAs($this->user)
        ->get(route('paciente.mis-consentimientos.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('paciente/consentimientos')
            ->where('teleconsultationConsent.isCurrent', true)
            ->where('contactEmail', config('privacy.contact_email'))
        );
});
