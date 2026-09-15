<?php

use App\Enums\VitalSignType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use App\Notifications\VitalSignOutOfRangeNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

test('agendar una cita notifica al paciente que tiene cuenta', function () {
    Notification::fake();

    $patientUser = User::factory()->create();
    $patientUser->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

    $this->actingAs($this->medico)->post(route('medico.citas.store'), [
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);

    Notification::assertSentTo($patientUser, AppointmentScheduledNotification::class);
});

test('agendar una cita a un paciente sin cuenta no falla', function () {
    Notification::fake();

    $patient = Patient::factory()->create(['user_id' => null]);

    $this->actingAs($this->medico)->post(route('medico.citas.store'), [
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'type' => Appointment::TYPE_IN_PERSON,
    ])->assertRedirect(route('medico.citas.index'));

    Notification::assertNothingSent();
});

test('una medición fuera de rango notifica al médico tratante', function () {
    Notification::fake();

    $patientUser = User::factory()->create();
    $patientUser->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $patientUser->id]);

    // El médico tratante es el de la cita más reciente del paciente.
    Appointment::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $this->medico->id]);

    $this->actingAs($patientUser)->post(route('paciente.signos-vitales.store'), [
        'type' => VitalSignType::OxygenSaturation->value,
        'value' => 86,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ]);

    Notification::assertSentTo($this->medico, VitalSignOutOfRangeNotification::class);
});

test('una medición dentro de rango no genera notificación', function () {
    Notification::fake();

    $patientUser = User::factory()->create();
    $patientUser->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
    Appointment::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $this->medico->id]);

    $this->actingAs($patientUser)->post(route('paciente.signos-vitales.store'), [
        'type' => VitalSignType::OxygenSaturation->value,
        'value' => 97,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ]);

    Notification::assertNothingSentTo($this->medico);
});

test('reenviar la misma medición desde la cola offline no vuelve a alertar al médico', function () {
    Notification::fake();

    $patientUser = User::factory()->create();
    $patientUser->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $patientUser->id]);
    Appointment::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $this->medico->id]);

    $payload = [
        'client_uuid' => Str::uuid()->toString(),
        'type' => VitalSignType::Glucose->value,
        'value' => 210,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ];

    $this->actingAs($patientUser)->postJson(route('paciente.signos-vitales.store'), $payload);
    $this->actingAs($patientUser)->postJson(route('paciente.signos-vitales.store'), $payload);

    Notification::assertSentToTimes($this->medico, VitalSignOutOfRangeNotification::class, 1);
});

test('un usuario puede marcar todas sus notificaciones como leídas', function () {
    $patient = Patient::factory()->create();
    Appointment::factory()->create(['patient_id' => $patient->id, 'doctor_id' => $this->medico->id]);

    $this->medico->notify(new AppointmentScheduledNotification(Appointment::first()));

    expect($this->medico->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->medico)->post(route('notificaciones.read-all'));

    expect($this->medico->fresh()->unreadNotifications()->count())->toBe(0);
});
