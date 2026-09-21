<?php

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('el dashboard del médico resume su propia agenda y no la de otros médicos', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $otroMedico = User::factory()->create();
    $otroMedico->assignRole('medico');

    $patient = Patient::factory()->create();
    ClinicalHistory::factory()->create(['patient_id' => $patient->id, 'ecnt_diagnosis' => 'Hipertensión arterial']);

    Appointment::factory()->count(2)->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDays(2),
    ]);

    Appointment::factory()->count(5)->create([
        'doctor_id' => $otroMedico->id,
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDays(2),
    ]);

    $this->actingAs($medico)
        ->get(route('medico.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('medico/dashboard')
            ->where('stats.appointmentsWeek', 2)
            ->has('upcomingAppointments', 2)
            ->has('ecntDistribution', 1)
        );
});

test('el dashboard del médico lista las mediciones fuera de rango como alertas', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $patient = Patient::factory()->create();

    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'type' => 'saturacion_oxigeno',
        'value' => 85,
        'unit' => '%',
    ]);

    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'type' => 'frecuencia_cardiaca',
        'value' => 72,
        'unit' => 'lpm',
    ]);

    $this->actingAs($medico)
        ->get(route('medico.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('alerts', 1)->where('alerts.0.status', 'bajo'));
});

/**
 * La teleconsulta solo se alcanzaba entrando a "Citas", así que el profesional
 * no tenía dónde ver de un vistazo las salas que le tocan hoy.
 */
test('el dashboard del médico lista las teleconsultas de hoy y no las de otros días', function () {
    // Sin congelar la hora, una cita "de hoy" cerca de medianoche cae en el día
    // siguiente y la prueba fallaría solo a ciertas horas.
    $this->travelTo(today()->setTime(9, 0));

    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $patient = Patient::factory()->create();

    $deHoy = Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now()->setTime(10, 0),
    ]);

    Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now()->addDay()->setTime(10, 0),
    ]);

    // Una presencial de hoy no tiene sala, así que tampoco entra en la tarjeta.
    Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_IN_PERSON,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now()->setTime(11, 0),
    ]);

    $this->actingAs($medico)
        ->get(route('medico.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('todayTeleconsultations', 1)
            ->where('todayTeleconsultations.0.id', $deHoy->id)
        );
});

test('la tarjeta solo deja entrar a la sala dentro de la ventana de la cita', function () {
    $this->travelTo(today()->setTime(9, 0));

    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $patient = Patient::factory()->create();

    $minutosAntes = (int) config('teleconsultation.join_window.minutes_before');

    $abierta = Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now()->addMinutes($minutosAntes - 1),
    ]);

    // Misma jornada, pero muy lejos de su ventana.
    $lejana = Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => today()->setTime(16, 0),
    ]);

    $this->actingAs($medico)
        ->get(route('medico.dashboard'))
        ->assertInertia(function ($page) use ($abierta, $lejana) {
            $porCita = collect($page->toArray()['props']['todayTeleconsultations'])->keyBy('id');

            expect($porCita[$abierta->id]['blockedReason'])->toBeNull();
            expect($porCita[$lejana->id]['blockedReason'])->not->toBeNull();
        });
});

test('el panel administrativo agrupa los usuarios por rol', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('usersByRole.admin', 1)
            ->where('usersByRole.medico', 1)
        );
});
