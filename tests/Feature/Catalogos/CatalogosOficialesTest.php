<?php

use App\Models\Code;
use App\Models\CodeSystem;
use App\Models\User;
use App\Rules\ActiveCode;
use App\Services\Catalogs\CodeCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Catálogos oficiales versionados. Los fixtures traen códigos FALSOS
 * (TEST001…): los reales se importan en el servidor desde su fuente.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function fixture(string $name): string
{
    return base_path("tests/Fixtures/catalogos/{$name}");
}

function importarPrueba(string $archivo, string $version = 'v1'): void
{
    test()->artisan('catalogos:importar', [
        'sistema' => 'prueba',
        'archivo' => fixture($archivo),
        '--version-catalogo' => $version,
        '--fuente' => 'fixture de pruebas',
    ])->assertSuccessful();
}

test('importa un CSV oficial con versión, fuente y huella del archivo', function () {
    importarPrueba('prueba-v1.csv');

    $sistema = CodeSystem::where('key', 'prueba')->sole();
    expect($sistema->version)->toBe('v1');
    expect($sistema->source)->toBe('fixture de pruebas');
    expect($sistema->source_sha256)->toBe(hash_file('sha256', fixture('prueba-v1.csv')));
    expect($sistema->imported_at)->not->toBeNull();

    expect(Code::where('code_system_id', $sistema->id)->count())->toBe(4);
    expect(Code::where('code', 'TEST003')->value('display'))->toContain('evaluación');
    expect(Code::where('code', 'TEST001')->first()->extra)->toBe(['capitulo' => 'Prueba']);
});

test('importar dos veces el mismo archivo no duplica nada', function () {
    importarPrueba('prueba-v1.csv');
    importarPrueba('prueba-v1.csv');

    expect(CodeSystem::where('key', 'prueba')->count())->toBe(1);
    expect(Code::count())->toBe(4);
    expect(Code::where('active', true)->count())->toBe(4);
});

test('una versión nueva desactiva lo que desaparece sin borrarlo y agrega lo nuevo', function () {
    importarPrueba('prueba-v1.csv', 'v1');
    importarPrueba('prueba-v2.csv', 'v2');

    $desaparecido = Code::where('code', 'TEST004')->sole();
    expect($desaparecido->active)->toBeFalse();
    expect($desaparecido->display)->toContain('cuatro');

    expect(Code::where('code', 'TEST005')->sole()->active)->toBeTrue();
    expect(Code::where('code', 'TEST001')->value('display'))->toContain('corregido');
    expect(Code::count())->toBe(5);
    expect(CodeSystem::where('key', 'prueba')->value('version'))->toBe('v2');

    // Si el código vuelve en una versión posterior, se reactiva.
    importarPrueba('prueba-v1.csv', 'v3');
    expect(Code::where('code', 'TEST004')->sole()->active)->toBeTrue();
});

test('importa un CodeSystem FHIR con su jerarquía y su versión', function () {
    $this->artisan('catalogos:importar', ['sistema' => 'prueba_fhir', 'archivo' => fixture('prueba-codesystem.json')])
        ->assertSuccessful();

    $sistema = CodeSystem::where('key', 'prueba_fhir')->sole();
    expect($sistema->version)->toBe('0.0.1-prueba');
    expect($sistema->source)->toBe('http://ejemplo.invalid/fhir/CodeSystem/prueba');
    expect(Code::where('code', 'TEST101')->value('parent_code'))->toBe('TEST100');
    expect(Code::where('code_system_id', $sistema->id)->count())->toBe(3);
});

test('un archivo de Excel se rechaza pidiendo exportarlo a CSV', function () {
    $excel = tempnam(sys_get_temp_dir(), 'cat').'.xlsx';
    file_put_contents($excel, 'no importa el contenido');

    $this->artisan('catalogos:importar', ['sistema' => 'prueba', 'archivo' => $excel])
        ->expectsOutputToContain('CSV')
        ->assertFailed();

    expect(CodeSystem::count())->toBe(0);
});

test('la búsqueda devuelve solo activos, por código o por nombre', function () {
    importarPrueba('prueba-v1.csv');
    importarPrueba('prueba-v2.csv', 'v2');

    $catalogo = app(CodeCatalog::class);

    expect($catalogo->search('prueba', 'TEST00')->pluck('code')->all())->toBe(['TEST001', 'TEST002', 'TEST003', 'TEST005']);
    expect($catalogo->search('prueba', 'evaluación')->pluck('code')->all())->toBe(['TEST003']);
    expect($catalogo->search('prueba', 'cuatro'))->toBeEmpty();
    expect($catalogo->search('no_existe', 'TEST'))->toBeEmpty();
});

test('la regla ActiveCode acepta activos y rechaza inactivos o inexistentes', function () {
    importarPrueba('prueba-v1.csv');
    importarPrueba('prueba-v2.csv', 'v2');

    $valida = fn (string $code) => Validator::make(['c' => $code], ['c' => [new ActiveCode('prueba')]])->passes();

    expect($valida('TEST001'))->toBeTrue();
    expect($valida('TEST004'))->toBeFalse();
    expect($valida('NOEXISTE'))->toBeFalse();
});

test('el buscador responde a médico y admin, y no a pacientes ni a invitados', function () {
    importarPrueba('prueba-v1.csv');
    $url = route('catalogos.buscar', ['sistema' => 'prueba', 'q' => 'TEST']);

    foreach (['medico', 'admin'] as $rol) {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        $this->actingAs($usuario)->getJson($url)->assertOk()->assertJsonCount(4, 'data');
    }

    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');
    $this->actingAs($paciente)->getJson($url)->assertForbidden();

    auth()->logout();
    $this->getJson($url)->assertUnauthorized();
});

test('la pantalla de catálogos es solo del admin y muestra lo importado y lo que falta', function () {
    importarPrueba('prueba-v1.csv');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.catalogos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/catalogos/index')
            ->where('systems.0.key', 'prueba')
            ->where('systems.0.activeCount', 4)
            ->where('expected.0.key', 'cie10')
        );

    $medico = User::factory()->create();
    $medico->assignRole('medico');
    $this->actingAs($medico)->get(route('admin.catalogos.index'))->assertForbidden();
});
