<?php

use App\Enums\BiologicalSex;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PractitionerProfile;
use App\Models\User;
use App\Services\AttentionRecordService;
use App\Services\InteroperabilityReadiness;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Qué falta para interoperar. Cada regla se prueba por separado, porque el
 * RDA y los RIPS van a usar el mismo servicio para explicar por qué no pueden
 * generar un documento.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->readiness = app(InteroperabilityReadiness::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function pacienteCompleto(array $extra = []): Patient
{
    return Patient::factory()->create(array_merge([
        'first_name' => 'Ana',
        'first_surname' => 'Rentería',
        'document_type' => 'CC',
        'biological_sex' => BiologicalSex::Female->value,
        'municipality_code' => 'TEST27001',
        'eapb_code' => 'TESTEPS01',
    ], $extra));
}

test('paciente: exige documento, primer nombre, primer apellido, sexo biológico, DIVIPOLA y EAPB', function () {
    expect($this->readiness->missingForPatient(pacienteCompleto()))->toBe([]);

    $incompleto = pacienteCompleto([
        'first_name' => null,
        'first_surname' => null,
        'biological_sex' => null,
        'municipality_code' => null,
        'eapb_code' => null,
    ]);

    expect($this->readiness->missingForPatient($incompleto))
        ->toBe(['primer nombre', 'primer apellido', 'sexo biológico', 'municipio (DIVIPOLA)', 'EAPB']);
});

test('paciente: "desconocido" es un valor válido del catálogo y no cuenta como faltante', function () {
    expect($this->readiness->missingForPatient(pacienteCompleto(['biological_sex' => BiologicalSex::Unknown->value])))->toBe([]);
});

test('médico: exige perfil profesional completo y verificación RETHUS', function () {
    expect($this->readiness->missingForPractitioner($this->medico))->toBe(['datos profesionales', 'verificación en RETHUS']);

    $perfil = PractitionerProfile::factory()->create(['user_id' => $this->medico->id, 'professional_registration' => null]);
    expect($this->readiness->missingForPractitioner($this->medico->fresh()))->toBe(['registro profesional', 'verificación en RETHUS']);

    $perfil->forceFill(['professional_registration' => 'RP-PRUEBA', 'rethus_verified_at' => now()])->save();
    expect($this->readiness->missingForPractitioner($this->medico->fresh()))->toBe([]);
});

test('institución: exige REPS y sede', function () {
    config()->set('nefrochoco.institution.reps_code', null);
    config()->set('nefrochoco.institution.site_code', '');
    expect($this->readiness->missingForInstitution())->toBe(['código de habilitación REPS', 'código de sede']);

    config()->set('nefrochoco.institution.reps_code', 'REPS-PRUEBA');
    config()->set('nefrochoco.institution.site_code', 'SEDE-PRUEBA');
    expect($this->readiness->missingForInstitution())->toBe([]);
});

test('atención: una cita cerrada sin diagnóstico principal vigente cuenta como faltante', function () {
    $patient = pacienteCompleto();
    $cita = Appointment::factory()->create(['doctor_id' => $this->medico->id, 'patient_id' => $patient->id, 'status' => Appointment::STATUS_COMPLETED]);

    expect($this->readiness->missingForAppointment($cita))->toBe(['diagnóstico principal CIE-10']);

    // Un diagnóstico solo relacionado no alcanza.
    app(AttentionRecordService::class)->record($cita, $this->medico, ['diagnoses' => [['cie10_code' => 'TESTDX2', 'role' => 'relacionado']]]);
    expect($this->readiness->missingForAppointment($cita->fresh()))->toBe(['diagnóstico principal CIE-10']);

    app(AttentionRecordService::class)->record($cita, $this->medico, ['diagnoses' => [['cie10_code' => 'TESTDX1', 'role' => 'principal']]]);
    expect($this->readiness->missingForAppointment($cita->fresh()))->toBe([]);
});

test('el servicio explica todo lo que impide generar el documento de una atención', function () {
    config()->set('nefrochoco.institution.reps_code', null);
    config()->set('nefrochoco.institution.site_code', null);
    $patient = pacienteCompleto(['eapb_code' => null]);
    $cita = Appointment::factory()->create(['doctor_id' => $this->medico->id, 'patient_id' => $patient->id, 'status' => Appointment::STATUS_COMPLETED]);

    expect($this->readiness->missingForDocument($cita))->toBe([
        'patient' => ['EAPB'],
        'practitioner' => ['datos profesionales', 'verificación en RETHUS'],
        'institution' => ['código de habilitación REPS', 'código de sede'],
        'attention' => ['diagnóstico principal CIE-10'],
    ]);
});

test('el tablero es solo del admin y no muestra contenido clínico', function () {
    $patient = pacienteCompleto(['eapb_code' => null]);
    $cita = Appointment::factory()->create(['doctor_id' => $this->medico->id, 'patient_id' => $patient->id, 'status' => Appointment::STATUS_COMPLETED]);
    $cita->forceFill(['consultation_reason' => 'Motivo clínico que no debe salir'])->save();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.interoperabilidad.index'))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/interoperabilidad/index')
        ->where('patients.count', 1)
        ->where('patients.items.0.missing', ['EAPB'])
        ->where('attentions.count', 1)
        ->where('attentions.items.0.patientName', $patient->full_name)
    );
    expect($response->getContent())->not->toContain('Motivo clínico');

    $this->actingAs($this->medico)->get(route('admin.interoperabilidad.index'))->assertForbidden();

    $pacienteUser = User::factory()->create();
    $pacienteUser->assignRole('paciente');
    $this->actingAs($pacienteUser)->get(route('admin.interoperabilidad.index'))->assertForbidden();
});
