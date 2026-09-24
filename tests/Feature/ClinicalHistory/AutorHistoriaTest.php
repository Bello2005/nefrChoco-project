<?php

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Res. 1644 de 2026: cada atención queda registrada identificando a quien la
 * hizo. La entrada de historia guarda su autor, y las viejas lo recuperan del
 * rastro de auditoría.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create(['name' => 'Dra. Yolanda Palacios']);
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
});

function registrarEntrada(User $medico, Patient $patient, array $extra = []): ClinicalHistory
{
    test()->actingAs($medico)->post(route('medico.pacientes.historia-clinica.store', $patient), [
        'ecnt_diagnosis' => 'Enfermedad renal crónica',
        'medical_history' => 'Hipertensión desde 2019.',
        ...$extra,
    ])->assertRedirect(route('medico.pacientes.show', $patient));

    return ClinicalHistory::where('patient_id', $patient->id)->latest('id')->firstOrFail();
}

/** Carga la migración de relleno tal como la correría el despliegue. */
function rellenarAutores(): void
{
    $migration = require database_path('migrations/2026_09_24_130100_backfill_author_id_on_clinical_histories.php');
    $migration->up();
}

test('la entrada guarda como autor al médico que la registra', function () {
    $entrada = registrarEntrada($this->medico, $this->patient);

    expect($entrada->author_id)->toBe($this->medico->id);
});

test('mandar el author_id de otro usuario no cambia el autor', function () {
    $otro = User::factory()->create();
    $otro->assignRole('medico');

    $entrada = registrarEntrada($this->medico, $this->patient, ['author_id' => $otro->id]);

    expect($entrada->author_id)->toBe($this->medico->id);
});

test('la pantalla y la versión imprimible muestran el nombre del autor', function () {
    $entrada = registrarEntrada($this->medico, $this->patient);

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.show', $entrada))
        ->assertInertia(fn (Assert $page) => $page->where('clinicalHistory.author.name', 'Dra. Yolanda Palacios'));

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertInertia(fn (Assert $page) => $page->where('patient.clinical_histories.0.author.name', 'Dra. Yolanda Palacios'));

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.print', $entrada))
        ->assertOk()
        ->assertSee('Registrada por Dra. Yolanda Palacios')
        ->assertSee('<span class="signer">Dra. Yolanda Palacios</span>', false);
});

test('el relleno recupera el autor de las entradas viejas desde la auditoría', function () {
    $entrada = registrarEntrada($this->medico, $this->patient);
    DB::table('clinical_histories')->where('id', $entrada->id)->update(['author_id' => null]);

    rellenarAutores();

    expect($entrada->fresh()->author_id)->toBe($this->medico->id);
});

test('el relleno no asigna autores que ya no existen, y esa entrada lo dice', function () {
    $borrado = User::factory()->create();
    $borrado->assignRole('medico');
    $entrada = registrarEntrada($borrado, $this->patient);

    // Un autor que se borró antes de que existieran las restricciones: su
    // evento sigue en la auditoría, pero la cuenta ya no.
    DB::table('clinical_histories')->where('id', $entrada->id)->update(['author_id' => null]);
    DB::table('model_has_roles')->where('model_id', $borrado->id)->delete();
    DB::table('users')->where('id', $borrado->id)->delete();

    rellenarAutores();

    expect($entrada->fresh()->author_id)->toBeNull();

    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.print', $entrada))
        ->assertSee('Autor no registrado (entrada anterior a este cambio)');
});
