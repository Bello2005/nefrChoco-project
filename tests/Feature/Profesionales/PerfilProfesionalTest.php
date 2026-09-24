<?php

use App\Models\ClinicalHistory;
use App\Models\PractitionerProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Datos RETHUS del médico y REPS de la institución (RDA del IHCE). La
 * verificación en RETHUS la hace una persona; aquí solo queda la constancia.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function perfilCompleto(array $extra = []): array
{
    return array_merge([
        'document_type' => 'CC',
        'document_number' => '80123456',
        'profession' => 'Medicina',
        'professional_registration' => 'RP-PRUEBA-001',
        'specialty' => 'Medicina interna',
    ], $extra);
}

test('el admin guarda los datos profesionales de un médico', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto())
        ->assertSessionHasNoErrors();

    $perfil = $this->medico->fresh()->practitionerProfile;
    expect($perfil->isComplete())->toBeTrue();
    expect($perfil->professional_registration)->toBe('RP-PRUEBA-001');
});

test('solo el admin marca la verificación RETHUS, y queda quién, cuándo y la nota', function () {
    $this->actingAs($this->admin)->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto());

    $otroMedico = User::factory()->create();
    $otroMedico->assignRole('medico');
    $this->actingAs($otroMedico)
        ->post(route('admin.usuarios.verificar-rethus', $this->medico), ['rethus_note' => 'intento'])
        ->assertForbidden();
    expect($this->medico->fresh()->practitionerProfile->rethus_verified_at)->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('admin.usuarios.verificar-rethus', $this->medico), ['rethus_note' => 'Consulta pública de ReTHUS: aparece activo.'])
        ->assertSessionHas('success');

    $perfil = $this->medico->fresh()->practitionerProfile;
    expect($perfil->rethus_verified_at)->not->toBeNull();
    expect($perfil->rethus_verified_by)->toBe($this->admin->id);
    expect($perfil->rethus_note)->toBe('Consulta pública de ReTHUS: aparece activo.');
});

test('la verificación exige una nota', function () {
    $this->actingAs($this->admin)->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto());

    $this->actingAs($this->admin)
        ->post(route('admin.usuarios.verificar-rethus', $this->medico), ['rethus_note' => ''])
        ->assertSessionHasErrors('rethus_note');
});

test('cambiar el documento o el registro borra la verificación anterior', function () {
    $this->actingAs($this->admin)->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto());
    $this->actingAs($this->admin)->post(route('admin.usuarios.verificar-rethus', $this->medico), ['rethus_note' => 'ok']);

    $this->actingAs($this->admin)->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto(['professional_registration' => 'RP-PRUEBA-002']));

    expect($this->medico->fresh()->practitionerProfile->rethus_verified_at)->toBeNull();
});

test('el documento del profesional es único', function () {
    PractitionerProfile::factory()->create(['document_number' => '80123456']);

    $this->actingAs($this->admin)
        ->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto())
        ->assertSessionHasErrors('document_number');
});

test('los datos profesionales quedan cifrados, salvo el documento, y la auditoría no guarda valores', function () {
    $this->actingAs($this->admin)->put(route('admin.usuarios.perfil-profesional', $this->medico), perfilCompleto());

    $crudo = DB::table('practitioner_profiles')->first();
    expect($crudo->document_number)->toBe('80123456');
    expect($crudo->professional_registration)->not->toContain('RP-PRUEBA');
    expect($crudo->specialty)->not->toContain('interna');

    $registro = Activity::where('subject_type', PractitionerProfile::class)->latest('id')->first();
    expect($registro->properties['campos'])->toContain('professional_registration');
    expect(DB::table('activity_log')->pluck('properties')->implode(' '))->not->toContain('RP-PRUEBA');
});

test('los datos profesionales solo aplican a cuentas de médico', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $this->actingAs($this->admin)
        ->put(route('admin.usuarios.perfil-profesional', $paciente), perfilCompleto())
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.usuarios.edit', $paciente))
        ->assertInertia(fn (Assert $page) => $page->where('practitioner', null));
});

test('el médico con el perfil incompleto ve el aviso, sin que se le bloquee la atención', function () {
    $this->actingAs($this->medico)
        ->get(route('medico.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('practitionerMissing', ['tus datos profesionales', 'la verificación en RETHUS']));

    $this->actingAs($this->medico)->get(route('medico.citas.index'))->assertOk();
});

test('la pantalla de la institución dice qué falta y los datos vienen de la configuración', function () {
    config()->set('nefrochoco.institution', ['name' => 'IPS de prueba', 'nit' => null, 'reps_code' => '', 'site_code' => null, 'municipality_code' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.institucion.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fields.0.value', 'IPS de prueba')
            // Un valor vacío en el .env también cuenta como faltante.
            ->where('missing', ['NIT', 'Código de habilitación REPS', 'Código de sede', 'Municipio de la sede (DIVIPOLA)'])
        );

    $this->actingAs($this->medico)->get(route('admin.institucion.index'))->assertForbidden();
});

test('la historia imprimible muestra el REPS solo si está configurado', function () {
    $historia = ClinicalHistory::factory()->create();

    config()->set('nefrochoco.institution.reps_code', null);
    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.print', $historia))
        ->assertOk()
        ->assertDontSee('Código de habilitación REPS');

    config()->set('nefrochoco.institution.name', 'IPS de prueba S.A.S.');
    config()->set('nefrochoco.institution.reps_code', 'REPS-DE-PRUEBA');
    $this->actingAs($this->medico)
        ->get(route('historias-clinicas.print', $historia))
        ->assertSee('IPS de prueba S.A.S.')
        ->assertSee('Código de habilitación REPS: REPS-DE-PRUEBA');
});
