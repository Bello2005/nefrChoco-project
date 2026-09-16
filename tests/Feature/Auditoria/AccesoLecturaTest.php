<?php

use App\Enums\VitalSignType;
use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\ClinicalAccessAuditor;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Registro de lecturas sobre datos clínicos (Ley 1581 de 2012).
 *
 * No basta con auditar la historia clínica: la ficha del paciente arrastra
 * historias, formularios y mediciones, y el telemonitoreo expone la serie
 * completa de signos vitales. Todas esas pantallas dejan constancia.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->patient = Patient::factory()->create(['full_name' => 'María Palacios']);
});

function accesosClinicos(): Collection
{
    return Activity::where('log_name', ClinicalAccessAuditor::LOG_NAME)->get();
}

function ultimoAccesoClinico(): ?Activity
{
    return Activity::where('log_name', ClinicalAccessAuditor::LOG_NAME)->latest('id')->first();
}

function registroDeAccesosEnCrudo(): string
{
    return DB::table('activity_log')->pluck('properties')->implode(' ');
}

test('consultar la ficha de un paciente queda registrado', function () {
    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertOk();

    $acceso = ultimoAccesoClinico();

    expect($acceso)->not->toBeNull();
    expect($acceso->event)->toBe('consultado');
    expect($acceso->causer_id)->toBe($this->medico->id);
    expect($acceso->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_PATIENT_FILE);
    expect($acceso->properties['paciente'])->toBe('María Palacios');
});

test('consultar un formulario clínico queda registrado', function () {
    $form = ClinicalForm::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
    ]);

    $this->actingAs($this->medico)
        ->get(route('medico.formularios-clinicos.show', $form))
        ->assertOk();

    expect(ultimoAccesoClinico()->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_CLINICAL_FORM);
});

test('consultar el telemonitoreo de un paciente queda registrado', function () {
    $this->actingAs($this->medico)
        ->get(route('medico.telemonitoreo.show', $this->patient))
        ->assertOk();

    expect(ultimoAccesoClinico()->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_MONITORING);
});

test('consultar una historia clínica sigue quedando registrado', function () {
    $history = ClinicalHistory::factory()->create(['patient_id' => $this->patient->id]);

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.show', $history))
        ->assertOk();

    expect(ultimoAccesoClinico()->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_HISTORY);
});

test('la versión imprimible de la historia también queda registrada', function () {
    $history = ClinicalHistory::factory()->create(['patient_id' => $this->patient->id]);

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.print', $history))
        ->assertOk();

    expect(ultimoAccesoClinico()->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_HISTORY);
});

test('un acceso denegado no genera registro de lectura', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');
    Patient::factory()->create(['user_id' => $paciente->id]);

    // El rol paciente no alcanza la zona médica.
    $this->actingAs($paciente)->get(route('medico.pacientes.show', $this->patient))->assertForbidden();
    $this->actingAs($paciente)->get(route('medico.telemonitoreo.show', $this->patient))->assertForbidden();

    expect(accesosClinicos())->toBeEmpty();
});

test('el registro de accesos nunca contiene valores cifrados', function () {
    $patient = Patient::factory()->create([
        'full_name' => 'Rosalba Mosquera',
        'phone' => '3009998877',
    ]);

    ClinicalHistory::factory()->create([
        'patient_id' => $patient->id,
        'ecnt_diagnosis' => 'DIAGNOSTICO-RESERVADO-XYZ',
    ]);

    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'recorded_by' => $this->medico->id,
        'type' => VitalSignType::Glucose->value,
        'notes' => 'NOTA-PRIVADA-DEL-PACIENTE',
    ]);

    $this->actingAs($this->medico)->get(route('medico.pacientes.show', $patient));
    $this->actingAs($this->medico)->get(route('medico.telemonitoreo.show', $patient));

    $crudo = registroDeAccesosEnCrudo();

    // El nombre sí viaja: se guarda sin cifrar porque se busca e indexa.
    expect($crudo)->toContain('Rosalba Mosquera');
    // Nada de lo que está cifrado en su tabla puede aparecer aquí.
    expect($crudo)->not->toContain('3009998877');
    expect($crudo)->not->toContain('DIAGNOSTICO-RESERVADO-XYZ');
    expect($crudo)->not->toContain('NOTA-PRIVADA-DEL-PACIENTE');
});

test('una lectura completa no se marca como refresco', function () {
    $this->actingAs($this->medico)->get(route('medico.pacientes.show', $this->patient));

    expect(ultimoAccesoClinico()->properties['parcial'])->toBeFalse();
});

test('una recarga parcial de Inertia también queda registrada, marcada como refresco', function () {
    // Una recarga parcial devuelve igualmente las props que se le piden, así
    // que el dato clínico vuelve a viajar al cliente y debe dejar constancia.
    $request = Request::create(route('medico.pacientes.show', $this->patient));
    $request->headers->set('X-Inertia-Partial-Data', 'patient');
    $request->setUserResolver(fn () => $this->medico);

    app(ClinicalAccessAuditor::class)->recordPatientFileAccess($this->patient, $request);

    $acceso = ultimoAccesoClinico();

    expect($acceso)->not->toBeNull();
    expect($acceso->properties['parcial'])->toBeTrue();
    expect($acceso->properties['recurso'])->toBe(ClinicalAccessAuditor::RESOURCE_PATIENT_FILE);
});

test('el panel de auditoría distingue el recurso consultado', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($this->medico)->get(route('medico.telemonitoreo.show', $this->patient));

    $this->actingAs($admin)
        ->get(route('admin.auditoria.index', ['tipo' => 'accesos']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/auditoria/index')
            ->where('activities.data.0.properties.recurso', ClinicalAccessAuditor::RESOURCE_MONITORING)
        );
});
