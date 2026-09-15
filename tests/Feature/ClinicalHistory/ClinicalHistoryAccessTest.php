<?php

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un paciente NO puede ver la historia clínica de otro paciente', function () {
    $userA = User::factory()->create();
    $userA->assignRole('paciente');
    $patientA = Patient::factory()->create(['user_id' => $userA->id]);
    ClinicalHistory::factory()->create(['patient_id' => $patientA->id]);

    $userB = User::factory()->create();
    $userB->assignRole('paciente');
    $patientB = Patient::factory()->create(['user_id' => $userB->id]);
    $historyB = ClinicalHistory::factory()->create(['patient_id' => $patientB->id]);

    $response = $this->actingAs($userA)->get(route('historias-clinicas.show', $historyB));

    $response->assertForbidden();
});

test('un paciente SÍ puede ver su propia historia clínica', function () {
    $user = User::factory()->create();
    $user->assignRole('paciente');
    $patient = Patient::factory()->create(['user_id' => $user->id]);
    $history = ClinicalHistory::factory()->create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->get(route('historias-clinicas.show', $history));

    $response->assertOk();
});

test('un médico puede ver la historia clínica de cualquier paciente', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $patient = Patient::factory()->create();
    $history = ClinicalHistory::factory()->create(['patient_id' => $patient->id]);

    $response = $this->actingAs($medico)->get(route('historias-clinicas.show', $history));

    $response->assertOk();
});
