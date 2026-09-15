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
