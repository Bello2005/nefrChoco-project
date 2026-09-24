<?php

use App\Enums\BiologicalSex;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientIdentityService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Identidad y datos mínimos de la Res. 866 de 2021. Los catálogos de estas
 * pruebas son FALSOS (TESTCC, TEST27001...): los reales se importan en el
 * servidor desde su fuente oficial.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function importarCatalogosDePrueba(): void
{
    foreach (['tipo_documento' => 'tipo-documento-prueba.csv', 'divipola' => 'divipola-prueba.csv', 'eapb' => 'eapb-prueba.csv'] as $sistema => $archivo) {
        test()->artisan('catalogos:importar', ['sistema' => $sistema, 'archivo' => base_path("tests/Fixtures/catalogos/{$archivo}")])
            ->assertSuccessful();
    }
}

function fichaCompleta(array $extra = []): array
{
    return array_merge([
        'first_name' => 'María',
        'middle_name' => 'Yolanda',
        'first_surname' => 'Palacios',
        'second_surname' => 'Mosquera',
        'document_type' => 'TESTCC',
        'document_number' => '1077000111',
        'birth_date' => '1970-02-02',
        'biological_sex' => 'femenino',
        'municipality_code' => 'TEST27001',
        'phone' => '3001234567',
        'eapb_code' => 'TESTEPS01',
        'ethnicity' => 'Texto libre de prueba',
        'occupation' => 'Agricultora',
    ], $extra);
}

test('con los catálogos importados, la ficha se valida contra ellos y el municipio sale del catálogo', function () {
    importarCatalogosDePrueba();

    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta())->assertSessionHasNoErrors();

    $patient = Patient::where('document_number', '1077000111')->sole();
    expect($patient->document_type)->toBe('TESTCC');
    expect($patient->municipality_code)->toBe('TEST27001');
    expect($patient->municipality)->toBe('Quibdó de prueba');
    expect($patient->eapb_code)->toBe('TESTEPS01');
});

test('se rechazan códigos que no están en el catálogo', function () {
    importarCatalogosDePrueba();

    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.store'), fichaCompleta([
            'document_type' => 'CC',
            'municipality_code' => 'NOEXISTE',
            'eapb_code' => 'NOEXISTE',
        ]))
        ->assertSessionHasErrors(['document_type', 'municipality_code', 'eapb_code']);

    expect(Patient::count())->toBe(0);
});

test('sin catálogos importados la ficha se sigue registrando como texto', function () {
    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.store'), fichaCompleta(['document_type' => 'CC', 'municipality_code' => null, 'municipality' => 'Quibdó', 'eapb_code' => 'Una EPS']))
        ->assertSessionHasNoErrors();

    expect(Patient::sole()->municipality)->toBe('Quibdó');
});

test('full_name se calcula con los nombres separados', function () {
    importarCatalogosDePrueba();
    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta());

    expect(Patient::sole()->full_name)->toBe('María Yolanda Palacios Mosquera');

    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta([
        'document_number' => '2',
        'middle_name' => null,
        'second_surname' => null,
    ]));

    expect(Patient::where('document_number', '2')->value('full_name'))->toBe('María Palacios');
});

test('los datos nuevos de la Res. 866 no quedan en claro en la base', function () {
    importarCatalogosDePrueba();
    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta());

    $crudo = DB::table('patients')->first();

    expect($crudo->ethnicity)->not->toContain('Texto libre');
    expect($crudo->occupation)->not->toContain('Agricultora');
    expect($crudo->eapb_code)->not->toBe('TESTEPS01');
    // Los nombres van en claro, como full_name: se buscan y se cotejan con el registro nacional.
    expect($crudo->first_name)->toBe('María');
});

test('la auditoría registra los nombres de los campos nuevos, sin sus valores', function () {
    importarCatalogosDePrueba();
    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta());

    $registro = Activity::where('subject_type', Patient::class)->where('event', 'created')->latest('id')->first();

    expect($registro->properties['campos'])->toContain('ethnicity', 'occupation', 'eapb_code', 'first_name');
    $auditoria = DB::table('activity_log')->pluck('properties')->implode(' ');
    expect($auditoria)->not->toContain('Agricultora');
    expect($auditoria)->not->toContain('Texto libre');
});

test('el relleno de identidad mapea lo exacto y marca lo ambiguo', function () {
    importarCatalogosDePrueba();

    $crear = fn (array $datos) => Patient::factory()->create(array_merge([
        'first_name' => null, 'middle_name' => null, 'first_surname' => null, 'second_surname' => null,
    ], $datos));

    $exacto = $crear(['full_name' => 'Rosa Elvira Mosquera Palacios', 'document_type' => 'cédula de prueba (falso)', 'municipality' => 'QUIBDO DE PRUEBA']);
    $choco = $crear(['full_name' => 'Ana Rentería', 'document_type' => 'TESTCC', 'municipality' => 'Mismo Nombre']);
    $ambiguo = $crear(['full_name' => 'Luis de la Cruz', 'document_type' => 'CC', 'municipality' => 'Doble Nombre', 'biological_sex' => null]);

    app(PatientIdentityService::class)->backfillAll();

    $exacto->refresh();
    expect($exacto->document_type)->toBe('TESTCC');
    expect($exacto->municipality_code)->toBe('TEST27001');
    expect($exacto->municipality)->toBe('Quibdó de prueba');
    expect([$exacto->first_name, $exacto->middle_name, $exacto->first_surname, $exacto->second_surname])
        ->toBe(['Rosa', 'Elvira', 'Mosquera', 'Palacios']);
    // Los nombres se proponen, pero siempre quedan para revisión.
    expect($exacto->identity_review_pending)->toBeTrue();
    expect($exacto->identity_review_reasons)->toBe(['nombres']);

    // Municipio homónimo: gana el del Chocó.
    expect($choco->fresh()->municipality_code)->toBe('TEST27002');

    $ambiguo->refresh();
    expect($ambiguo->document_type)->toBe('CC');
    expect($ambiguo->municipality_code)->toBeNull();
    expect($ambiguo->first_surname)->toBe('de la Cruz');
    expect($ambiguo->identity_review_reasons)->toBe(['nombres', 'tipo_documento', 'municipio', 'sexo_biologico']);
});

test('sin catálogos, el relleno marca todo lo que no pudo mapear, y al importarlos se vuelve a correr', function () {
    $patient = Patient::factory()->create(['first_name' => null, 'full_name' => 'Ana Rentería', 'document_type' => 'TESTCC', 'municipality' => 'Quibdó de prueba']);

    app(PatientIdentityService::class)->backfillAll();
    expect($patient->fresh()->identity_review_reasons)->toBe(['nombres', 'tipo_documento', 'municipio']);

    importarCatalogosDePrueba();
    $this->artisan('pacientes:revisar-identidad')->assertSuccessful();

    expect($patient->fresh()->identity_review_reasons)->toBe(['nombres']);
    expect($patient->fresh()->municipality_code)->toBe('TEST27001');
});

test('la migración de datos corre el relleno sobre las fichas existentes', function () {
    $patient = Patient::factory()->create(['first_name' => null, 'full_name' => 'Pedro Pablo Moreno Córdoba']);

    $migration = require database_path('migrations/2026_09_24_180100_backfill_patient_identity.php');
    $migration->up();

    expect($patient->fresh()->first_name)->toBe('Pedro');
    expect($patient->fresh()->middle_name)->toBe('Pablo');
    expect($patient->fresh()->identity_review_pending)->toBeTrue();
});

test('la búsqueda del padrón sigue viendo el nombre completo y el documento', function () {
    importarCatalogosDePrueba();
    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaCompleta());

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('patients.0.full_name', 'María Yolanda Palacios Mosquera')
            ->where('patients.0.document_number', '1077000111')
        );

    expect(Patient::where('full_name', 'like', '%Palacios%')->where('document_number', '1077000111')->exists())->toBeTrue();
});

test('indeterminado y desconocido se aceptan, tienen su código FHIR y no calculan TFGe', function () {
    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.store'), fichaCompleta(['document_type' => 'CC', 'municipality_code' => null, 'municipality' => 'Quibdó', 'eapb_code' => null, 'biological_sex' => 'indeterminado']))
        ->assertSessionHasNoErrors();

    $patient = Patient::sole();
    expect($patient->biological_sex)->toBe(BiologicalSex::Indeterminate);
    expect(BiologicalSex::Indeterminate->fhirCode())->toBe('other');
    expect(BiologicalSex::Unknown->fhirCode())->toBe('unknown');
    expect(BiologicalSex::Female->fhirCode())->toBe('female');

    $this->actingAs($this->medico)
        ->post(route('medico.formularios-clinicos.store'), [
            'patient_id' => $patient->id,
            'form_type' => 'seguimiento_erc',
            'answers' => ['creatinina' => 0.8, 'fecha_laboratorio' => now()->subDay()->format('Y-m-d')],
        ])
        ->assertSessionHasErrors('patient_id');

    expect($patient->clinicalForms()->count())->toBe(0);
});

test('fichas por revisar: la ven admin y médico, no el paciente, y guardarla la saca de la lista', function () {
    $patient = Patient::factory()->create();
    $patient->forceFill(['identity_review_pending' => true, 'identity_review_reasons' => ['nombres']])->save();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach ([$admin, $this->medico] as $usuario) {
        $this->actingAs($usuario)
            ->get(route('fichas-por-revisar.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('patients.0.id', $patient->id)->where('patients.0.reasons', ['nombres']));
    }

    $pacienteUser = User::factory()->create();
    $pacienteUser->assignRole('paciente');
    $this->actingAs($pacienteUser)->get(route('fichas-por-revisar.index'))->assertForbidden();

    $this->actingAs($admin)
        ->put(route('fichas-por-revisar.update', $patient), fichaCompleta([
            'document_type' => 'CC', 'municipality_code' => null, 'municipality' => 'Quibdó', 'eapb_code' => null,
            'document_number' => $patient->document_number,
        ]))
        ->assertRedirect(route('fichas-por-revisar.index'));

    expect($patient->fresh()->identity_review_pending)->toBeFalse();
    expect($patient->fresh()->identity_review_reasons)->toBeNull();
});
