<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un paciente ve únicamente sus propias citas y no las de otro paciente', function () {
    $userA = User::factory()->create();
    $userA->assignRole('paciente');
    $patientA = Patient::factory()->create(['user_id' => $userA->id]);
    $appointmentA = Appointment::factory()->create(['patient_id' => $patientA->id]);

    $userB = User::factory()->create();
    $userB->assignRole('paciente');
    $patientB = Patient::factory()->create(['user_id' => $userB->id]);
    Appointment::factory()->create(['patient_id' => $patientB->id]);

    $response = $this->actingAs($userA)->get(route('paciente.mis-citas.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('paciente/mis-citas/index')
        ->has('appointments', 1)
        ->where('appointments.0.id', $appointmentA->id)
    );
});
