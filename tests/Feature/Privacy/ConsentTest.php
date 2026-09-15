<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');
    $this->patient = Patient::factory()->withoutConsent()->create(['user_id' => $this->user->id]);
});

test('un paciente sin autorización es enviado a la pantalla de consentimiento', function () {
    $this->actingAs($this->user)
        ->get(route('paciente.dashboard'))
        ->assertRedirect(route('paciente.consentimiento.show'));
});

test('al autorizar queda registrada la fecha y la versión de la política', function () {
    $this->actingAs($this->user)
        ->post(route('paciente.consentimiento.store'), ['accepted' => true])
        ->assertRedirect(route('paciente.dashboard'));

    $patient = $this->patient->fresh();

    expect($patient->consent_accepted_at)->not->toBeNull();
    expect($patient->consent_version)->toBe(config('privacy.consent_version'));
    expect($patient->hasCurrentConsent())->toBeTrue();
});

test('sin marcar la casilla no se registra la autorización', function () {
    $this->actingAs($this->user)
        ->post(route('paciente.consentimiento.store'), ['accepted' => false])
        ->assertSessionHasErrors('accepted');

    expect($this->patient->fresh()->consent_accepted_at)->toBeNull();
});

test('con autorización vigente el paciente entra normalmente', function () {
    $this->patient->update([
        'consent_accepted_at' => now(),
        'consent_version' => config('privacy.consent_version'),
    ]);

    $this->actingAs($this->user)->get(route('paciente.dashboard'))->assertOk();
});

test('si cambia la versión de la política se vuelve a pedir el consentimiento', function () {
    $this->patient->update([
        'consent_accepted_at' => now()->subYear(),
        'consent_version' => '2024-01',
    ]);

    config(['privacy.consent_version' => '2026-01']);

    $this->actingAs($this->user)
        ->get(route('paciente.dashboard'))
        ->assertRedirect(route('paciente.consentimiento.show'));
});

test('el consentimiento queda en la auditoría', function () {
    $this->actingAs($this->user)->post(route('paciente.consentimiento.store'), ['accepted' => true]);

    $activity = Activity::where('log_name', 'consentimiento')->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('autorizado');
    expect($activity->causer_id)->toBe($this->user->id);
    expect($activity->properties['version'])->toBe(config('privacy.consent_version'));
});

test('el personal de la IPS no pasa por el muro de consentimiento', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($medico)->get(route('medico.dashboard'))->assertOk();
});
