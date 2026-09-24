<?php

use App\Models\Code;
use App\Models\User;
use App\Services\AttentionRecordService;
use App\Services\Catalogs\CodeCatalog;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Catálogos del IHCE en los formularios: los códigos que solo agrupan no se
 * pueden elegir, y los catálogos pequeños se muestran como lista. Los
 * fixtures traen códigos FALSOS (TESTDOC1, TESTZ1…).
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function importarFixture(string $sistema, string $archivo): void
{
    test()->artisan('catalogos:importar', ['sistema' => $sistema, 'archivo' => base_path("tests/Fixtures/catalogos/{$archivo}")])
        ->assertSuccessful();
}

test('en tipos de documento los códigos que agrupan quedan inactivos y los documentos activos', function () {
    importarFixture('tipo_documento', 'jerarquia-prueba.json');

    $catalogo = app(CodeCatalog::class);

    expect($catalogo->isActive('tipo_documento', 'TESTGRUPO1'))->toBeFalse();
    expect($catalogo->isActive('tipo_documento', 'TESTGRUPO2'))->toBeFalse();
    expect($catalogo->isActive('tipo_documento', 'TESTDOC1'))->toBeTrue();
    expect($catalogo->isActive('tipo_documento', 'TESTDOC3'))->toBeTrue();
    // Se guarda completo: el agrupador sigue teniendo nombre.
    expect($catalogo->display('tipo_documento', 'TESTGRUPO1'))->toContain('agrupa');
    expect(Code::where('code', 'TESTDOC1')->value('parent_code'))->toBe('TESTGRUPO1');
});

test('un catálogo sin leaves_only conserva activos sus agrupadores', function () {
    importarFixture('prueba_jerarquia', 'jerarquia-prueba.json');

    expect(app(CodeCatalog::class)->isActive('prueba_jerarquia', 'TESTGRUPO1'))->toBeTrue();
});

test('un catálogo pequeño se ofrece completo y en el orden del archivo; uno grande, no', function () {
    importarFixture('zona_residencia', 'zona-prueba.csv');

    $catalogo = app(CodeCatalog::class);

    expect($catalogo->options('zona_residencia'))->toBe([
        ['code' => 'TESTZ2', 'display' => 'Zona de prueba dos (FALSO)'],
        ['code' => 'TESTZ1', 'display' => 'Zona de prueba uno (FALSO)'],
    ]);
    expect($catalogo->options('no_importado'))->toBeNull();

    config(['catalogs.select_max' => 1]);
    expect($catalogo->options('zona_residencia'))->toBeNull();
});

test('la ficha recibe la lista del catálogo pequeño y valida contra él', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');
    importarFixture('zona_residencia', 'zona-prueba.csv');

    $this->actingAs($medico)->get(route('medico.pacientes.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalogs.residence_zone', 'zona_residencia')
            ->where('catalogOptions.residence_zone.0.code', 'TESTZ2')
            ->where('catalogOptions.residence_zone.1.code', 'TESTZ1')
            ->missing('catalogOptions.occupation'));

    $ficha = [
        'first_name' => 'Ana',
        'first_surname' => 'Rentería',
        'document_type' => 'CC',
        'document_number' => '1077000222',
        'birth_date' => '1965-05-05',
        'biological_sex' => 'femenino',
        'municipality' => 'Quibdó',
        'phone' => '3001234567',
    ];

    $this->actingAs($medico)->post(route('medico.pacientes.store'), [...$ficha, 'residence_zone' => 'Rural'])
        ->assertSessionHasErrors('residence_zone');

    $this->actingAs($medico)->post(route('medico.pacientes.store'), [...$ficha, 'residence_zone' => 'TESTZ1'])
        ->assertSessionHasNoErrors();
});

test('el registro de la atención recibe las listas de finalidad, causa externa y tipo de diagnóstico', function () {
    importarFixture('finalidad_consulta', 'zona-prueba.csv');

    $disponibles = app(AttentionRecordService::class)->availability();

    expect($disponibles['purpose'])->toBe('finalidad_consulta');
    expect($disponibles['options']['purpose'])->toHaveCount(2);
    expect($disponibles['options'])->not->toHaveKey('externalCause');
    expect($disponibles['externalCause'])->toBeNull();
});

test('el tipo de afiliación se elige de la lista de tipo de usuario del RIPS', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');
    importarFixture('tipo_usuario', 'sispro-prueba.csv');

    // Los códigos deshabilitados en SISPRO no se ofrecen.
    $this->actingAs($medico)->get(route('medico.pacientes.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('catalogs.affiliation_type', 'tipo_usuario')
            ->where('catalogOptions.affiliation_type', fn ($opciones) => collect($opciones)->pluck('code')->all() === ['TESTSP1', 'TESTSP2']));

    $ficha = [
        'first_name' => 'Ana',
        'first_surname' => 'Rentería',
        'document_type' => 'CC',
        'document_number' => '1077000333',
        'birth_date' => '1965-05-05',
        'biological_sex' => 'femenino',
        'municipality' => 'Quibdó',
        'phone' => '3001234567',
    ];

    $this->actingAs($medico)->post(route('medico.pacientes.store'), [...$ficha, 'affiliation_type' => 'Subsidiado'])
        ->assertSessionHasErrors('affiliation_type');

    $this->actingAs($medico)->post(route('medico.pacientes.store'), [...$ficha, 'affiliation_type' => 'TESTSP1'])
        ->assertSessionHasNoErrors();
});

test('en la finalidad solo se pueden elegir los códigos habilitados que SISPRO marca para consultas', function () {
    importarFixture('finalidad_consulta', 'finalidad-prueba.csv');

    $catalogo = app(CodeCatalog::class);

    expect($catalogo->isActive('finalidad_consulta', 'TESTF1'))->toBeTrue();
    expect($catalogo->isActive('finalidad_consulta', 'TESTF2'))->toBeFalse();
    expect($catalogo->isActive('finalidad_consulta', 'TESTF3'))->toBeFalse();
    // Los que no aplican se guardan igual, para mostrar registros viejos.
    expect($catalogo->display('finalidad_consulta', 'TESTF2'))->toContain('PROCEDIMIENTOS');
    expect(app(AttentionRecordService::class)->availability()['options']['purpose'])
        ->toBe([['code' => 'TESTF1', 'display' => 'FINALIDAD DE PRUEBA PARA CONSULTAS (FALSO)']]);
});
