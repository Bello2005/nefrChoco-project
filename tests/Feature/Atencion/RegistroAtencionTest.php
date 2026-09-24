<?php

use App\Models\Appointment;
use App\Models\AppointmentDiagnosis;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Teleconsultation;
use App\Models\User;
use App\Services\AttentionRecordService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Registro estructurado de la atención (RDA y RIPS). Los catálogos son
 * FALSOS (TESTDX1, TESTPX1...): los reales se importan en el servidor.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
});

function importarCodigos(string $sistema, string $archivo): void
{
    test()->artisan('catalogos:importar', ['sistema' => $sistema, 'archivo' => base_path("tests/Fixtures/catalogos/{$archivo}")])->assertSuccessful();
}

function teleconsultaAbierta(User $medico, Patient $patient): Appointment
{
    $cita = Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => $patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ]);
    Teleconsultation::factory()->create(['appointment_id' => $cita->id]);

    return $cita;
}

function cierre(array $extra = []): array
{
    return array_merge([
        'notes' => 'Nota de la consulta.',
        'consultation_reason' => 'Control de prueba',
        'diagnoses' => [['cie10_code' => 'TESTDX1', 'role' => 'principal']],
    ], $extra);
}

test('no se cierra la teleconsulta sin diagnóstico principal, con un mensaje claro', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), ['notes' => 'Nota sin diagnóstico.'])
        ->assertSessionHasErrors(['diagnoses' => 'Registra el diagnóstico principal (CIE-10) para cerrar la atención.']);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), cierre(['diagnoses' => [['cie10_code' => 'TESTDX1', 'role' => 'relacionado']]]))
        ->assertSessionHasErrors('diagnoses');

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);
});

test('cerrar con diagnóstico guarda el registro completo con su autor', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    importarCodigos('cups', 'cups-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), cierre([
            'diagnoses' => [['cie10_code' => 'TESTDX1', 'role' => 'principal'], ['cie10_code' => 'TESTDX2', 'role' => 'relacionado']],
            'procedures' => [['cups_code' => 'TESTPX1', 'quantity' => 1]],
            'medications' => [['description' => 'Medicamento de prueba', 'dose' => '1 tableta', 'frequency' => 'cada 12 horas']],
            'no_known_allergies' => true,
        ]))
        ->assertRedirect(route('medico.citas.index'));

    $cita->refresh();
    expect($cita->status)->toBe(Appointment::STATUS_COMPLETED);
    expect($cita->consultation_reason)->toBe('Control de prueba');
    expect($cita->diagnoses)->toHaveCount(2);
    expect($cita->diagnoses->first()->author_id)->toBe($this->medico->id);
    expect($cita->procedures->first()->cups_code)->toBe('TESTPX1');
    expect($cita->medications->first()->description)->toBe('Medicamento de prueba');
    expect(PatientAllergy::where('patient_id', $this->patient->id)->sole()->no_known_allergies)->toBeTrue();
});

test('se rechazan códigos inexistentes o inactivos', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    importarCodigos('cie10', 'cie10-prueba-v2.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    foreach (['NOEXISTE', 'TESTDX3'] as $codigo) {
        $this->actingAs($this->medico)
            ->post(route('medico.citas.teleconsulta.complete', $cita), cierre(['diagnoses' => [['cie10_code' => $codigo, 'role' => 'principal']]]))
            ->assertSessionHasErrors('diagnoses.0.cie10_code');
    }

    expect(AppointmentDiagnosis::count())->toBe(0);
});

test('los códigos quedan cifrados en la base y la auditoría no guarda valores', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $cita), cierre());

    $crudo = DB::table('appointment_diagnoses')->first();
    expect($crudo->cie10_code)->not->toBe('TESTDX1');
    expect($crudo->role)->toBe('principal');
    expect(DB::table('appointments')->where('id', $cita->id)->value('consultation_reason'))->not->toContain('Control');

    $registro = Activity::where('subject_type', AppointmentDiagnosis::class)->sole();
    expect($registro->causer_id)->toBe($this->medico->id);
    expect($registro->properties['campos'])->toContain('cie10_code');
    expect(DB::table('activity_log')->pluck('properties')->implode(' '))->not->toContain('TESTDX1');
});

test('después del cierre nada se edita: ni la cita ni el registro', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);
    $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $cita), cierre());

    // Un segundo cierre no reescribe ni duplica.
    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), cierre(['diagnoses' => [['cie10_code' => 'TESTDX2', 'role' => 'principal']]]))
        ->assertSessionHas('error');

    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), ['patient_id' => $this->patient->id, 'scheduled_at' => now()->format('Y-m-d H:i:s'), 'type' => 'teleconsulta', 'status' => 'completada'])
        ->assertForbidden();

    expect(AppointmentDiagnosis::count())->toBe(1);

    $rutas = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->filter(fn ($uri) => str_contains($uri, 'diagnostic'));
    expect($rutas)->toBeEmpty();
});

test('una aclaración agrega el diagnóstico corregido sin borrar el original', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);
    $this->actingAs($this->medico)->post(route('medico.citas.teleconsulta.complete', $cita), cierre());
    $original = AppointmentDiagnosis::sole();

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.aclaraciones.store', $cita), [
            'body' => 'Se corrige el diagnóstico principal.',
            'corrected_diagnosis' => ['replaces_id' => $original->id, 'cie10_code' => 'TESTDX2'],
        ])
        ->assertSessionHas('success');

    $correccion = AppointmentDiagnosis::where('replaces_id', $original->id)->sole();
    expect($correccion->cie10_code)->toBe('TESTDX2');
    expect($correccion->role)->toBe('principal');
    expect($correccion->author_id)->toBe($this->medico->id);
    expect($original->fresh()->cie10_code)->toBe('TESTDX1');

    $vigentes = app(AttentionRecordService::class)->currentDiagnoses($cita->fresh());
    expect($vigentes->pluck('cie10_code')->all())->toBe(['TESTDX2']);

    $historia = ClinicalHistory::factory()->create(['patient_id' => $this->patient->id]);
    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.show', $historia))
        ->assertInertia(fn (Assert $page) => $page
            ->where('teleconsultationNotes.0.diagnoses.0.isCurrent', false)
            ->where('teleconsultationNotes.0.diagnoses.1.isCorrection', true)
        );
});

test('solo el médico de la cita registra la atención', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $otro = User::factory()->create();
    $otro->assignRole('medico');

    $this->actingAs($otro)->post(route('medico.citas.teleconsulta.complete', $cita), cierre())->assertForbidden();

    expect(AppointmentDiagnosis::count())->toBe(0);
});

test('marcar atendida una cita presencial también exige el diagnóstico principal', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = Appointment::factory()->create(['doctor_id' => $this->medico->id, 'patient_id' => $this->patient->id, 'type' => Appointment::TYPE_IN_PERSON]);
    $datos = ['patient_id' => $this->patient->id, 'scheduled_at' => now()->format('Y-m-d H:i:s'), 'type' => 'presencial', 'status' => 'completada'];

    $this->actingAs($this->medico)->put(route('medico.citas.update', $cita), $datos)->assertSessionHasErrors('diagnoses');
    expect($cita->fresh()->status)->toBe(Appointment::STATUS_SCHEDULED);

    $this->actingAs($this->medico)
        ->put(route('medico.citas.update', $cita), [...$datos, 'diagnoses' => [['cie10_code' => 'TESTDX1', 'role' => 'principal']]])
        ->assertRedirect(route('medico.citas.index'));

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_COMPLETED);
    expect($cita->diagnoses()->count())->toBe(1);
});

test('el campo CIE-11 solo se acepta si ese catálogo está importado, sin equivalencias automáticas', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), cierre(['diagnoses' => [['cie10_code' => 'TESTDX1', 'cie11_code' => 'TESTC11', 'role' => 'principal']]]))
        ->assertSessionHasErrors('diagnoses.0.cie11_code');

    importarCodigos('cie11', 'cie11-prueba.csv');
    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), cierre(['diagnoses' => [['cie10_code' => 'TESTDX1', 'cie11_code' => 'TESTC11', 'role' => 'principal']]]))
        ->assertSessionHasNoErrors();

    expect(AppointmentDiagnosis::sole()->cie11_code)->toBe('TESTC11');
});

test('sin el catálogo CIE-10 importado la consulta se puede cerrar igual', function () {
    $cita = teleconsultaAbierta($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->post(route('medico.citas.teleconsulta.complete', $cita), ['notes' => 'Nota sin catálogo.'])
        ->assertSessionHasNoErrors();

    expect($cita->fresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

test('una entrada de historia acepta diagnósticos CIE-10 opcionales y se muestran e imprimen', function () {
    importarCodigos('cie10', 'cie10-prueba.csv');

    $this->actingAs($this->medico)->post(route('medico.pacientes.historia-clinica.store', $this->patient), [
        'ecnt_diagnosis' => 'Texto que se conserva',
        'diagnoses' => ['TESTDX1'],
    ])->assertSessionHasNoErrors();

    $historia = ClinicalHistory::sole();
    expect($historia->ecnt_diagnosis)->toBe('Texto que se conserva');
    expect($historia->diagnoses->first()->cie10_code)->toBe('TESTDX1');

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.show', $historia))
        ->assertInertia(fn (Assert $page) => $page->where('historyDiagnoses.0.code', 'TESTDX1'));

    $this->actingAs($this->medico)->get(route('historias-clinicas.print', $historia))->assertSee('TESTDX1');

    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.historia-clinica.store', $this->patient), ['diagnoses' => ['NOEXISTE']])
        ->assertSessionHasErrors('diagnoses.0');
});
