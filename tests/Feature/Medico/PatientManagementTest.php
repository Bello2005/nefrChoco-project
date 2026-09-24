<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

test('un médico puede registrar un paciente', function () {
    $response = $this->actingAs($this->medico)->post(route('medico.pacientes.store'), [
        'first_name' => 'María',
        'first_surname' => 'Palacios',
        'document_type' => 'CC',
        'document_number' => '1077123456',
        'birth_date' => '1980-05-10',
        'biological_sex' => 'femenino',
        'municipality' => 'Quibdó',
        'phone' => '3001234567',
        'emergency_contact_name' => 'Pedro Palacios',
        'emergency_contact_phone' => '3007654321',
    ]);

    $patient = Patient::where('document_number', '1077123456')->first();
    expect($patient)->not->toBeNull();
    // full_name se arma de los nombres separados, para que la búsqueda siga funcionando.
    expect($patient->full_name)->toBe('María Palacios');
    $response->assertRedirect(route('medico.pacientes.show', $patient));
});

test('un médico puede crear una historia clínica básica para un paciente', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($this->medico)->post(route('medico.pacientes.historia-clinica.store', $patient), [
        'ecnt_diagnosis' => 'Hipertensión arterial',
        'medical_history' => 'Sin antecedentes relevantes.',
        'allergies' => 'Ninguna conocida',
        'current_medication' => 'Losartán 50mg',
    ]);

    $response->assertRedirect(route('medico.pacientes.show', $patient));
    expect($patient->clinicalHistories()->count())->toBe(1);
    expect($patient->clinicalHistories()->first()->ecnt_diagnosis)->toBe('Hipertensión arterial');
});

test('un médico puede agendar una cita de teleconsulta y se genera una sala única', function () {
    $patient = Patient::factory()->create();

    $response = $this->actingAs($this->medico)->post(route('medico.citas.store'), [
        'patient_id' => $patient->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);

    $response->assertRedirect(route('medico.citas.index'));

    $appointment = Appointment::where('patient_id', $patient->id)->first();
    expect($appointment)->not->toBeNull();
    expect($appointment->teleconsultation)->not->toBeNull();
    expect($appointment->teleconsultation->room_name)->toStartWith('nefrochoco-');
});

test('un médico puede entrar a la sala de teleconsulta de una cita', function () {
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'scheduled_at' => now(),
    ]);

    $response = $this->actingAs($this->medico)->get(route('medico.citas.teleconsulta', $appointment));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('medico/teleconsulta/show'));
});

test('un usuario con rol paciente no puede acceder a la gestión de pacientes', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $response = $this->actingAs($paciente)->get(route('medico.pacientes.index'));

    $response->assertForbidden();
});
