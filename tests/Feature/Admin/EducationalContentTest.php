<?php

use App\Models\EducationalContent;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('un admin puede publicar contenido educativo', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.educativo.store'), [
        'title' => 'Cómo cuidar tus riñones',
        'description' => 'Recomendaciones básicas de hidratación y alimentación.',
        'type' => EducationalContent::TYPE_ARTICLE,
        'url_or_path' => 'https://www.minsalud.gov.co/recurso',
        'ecnt_category' => 'enfermedad_renal',
    ]);

    $response->assertRedirect(route('admin.educativo.index'));
    expect(EducationalContent::where('title', 'Cómo cuidar tus riñones')->exists())->toBeTrue();
});

test('el enlace del contenido educativo debe ser una url válida', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.educativo.store'), [
        'title' => 'Material sin enlace válido',
        'type' => EducationalContent::TYPE_VIDEO,
        'url_or_path' => 'no-es-una-url',
        'ecnt_category' => 'diabetes',
    ]);

    $response->assertSessionHasErrors('url_or_path');
    expect(EducationalContent::count())->toBe(0);
});

test('un paciente ve el contenido educativo filtrado por categoría', function () {
    EducationalContent::factory()->create(['ecnt_category' => 'diabetes']);
    EducationalContent::factory()->count(2)->create(['ecnt_category' => 'hipertension']);

    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');
    Patient::factory()->create(['user_id' => $paciente->id]);

    $this->actingAs($paciente)
        ->get(route('paciente.educativo.index', ['categoria' => 'hipertension']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('paciente/educativo/index')->has('contents', 2));
});

test('un médico no puede administrar el contenido educativo', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($medico)->get(route('admin.educativo.index'))->assertForbidden();
});

/**
 * El compromiso del anteproyecto es material "accesible sin conexión continua".
 * Un enlace externo no lo cumple: el service worker solo intercepta el mismo
 * origen, así que el contenido propio es lo único que viaja con la aplicación.
 */
test('el contenido propio se lee dentro de la aplicación', function () {
    $content = EducationalContent::factory()->create([
        'body' => "## Cuida tus riñones\n\nToma los medicamentos **todos los días**.",
        'available_offline' => true,
        'url_or_path' => null,
    ]);

    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');
    Patient::factory()->create(['user_id' => $paciente->id]);

    $this->actingAs($paciente)
        ->get(route('paciente.educativo.show', $content))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('paciente/educativo/show')
            ->where('content.availableOffline', true)
            ->where('content.bodyHtml', fn (string $html) => str_contains($html, '<h2>Cuida tus riñones</h2>')
                && str_contains($html, '<strong>todos los días</strong>'))
        );
});

test('un contenido que solo es enlace externo no tiene pantalla de lectura', function () {
    $content = EducationalContent::factory()->create([
        'body' => null,
        'url_or_path' => 'https://www.minsalud.gov.co/',
    ]);

    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');
    Patient::factory()->create(['user_id' => $paciente->id]);

    $this->actingAs($paciente)
        ->get(route('paciente.educativo.show', $content))
        ->assertNotFound();
});

test('el HTML escrito a mano en el contenido se descarta', function () {
    $content = EducationalContent::factory()->create([
        'body' => "<script>alert(1)</script>\n\nTexto legítimo\n\n<img src=x onerror=alert(1)>",
    ]);

    // CommonMark trata el bloque HTML como una unidad y, con `html_input: strip`,
    // lo descarta entero. El texto en Markdown sobrevive; el código no.
    expect($content->body_html)
        ->not->toContain('script')
        ->not->toContain('onerror')
        ->toContain('Texto legítimo');
});

test('un contenido sin cuerpo propio no puede marcarse como disponible sin conexión', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.educativo.store'), [
        'title' => 'Video de presión arterial',
        'description' => 'Material alojado fuera de la plataforma.',
        'type' => EducationalContent::TYPE_VIDEO,
        'url_or_path' => 'https://www.minsalud.gov.co/',
        'available_offline' => true,
    ] + ['ecnt_category' => 'hipertension'])->assertSessionHasNoErrors();

    expect(EducationalContent::where('title', 'Video de presión arterial')->sole()->available_offline)->toBeFalse();
});

test('un contenido necesita cuerpo propio o enlace', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.educativo.store'), [
        'title' => 'Contenido vacío',
        'description' => 'Sin cuerpo y sin enlace.',
        'type' => EducationalContent::TYPE_ARTICLE,
        'ecnt_category' => 'hipertension',
    ])->assertSessionHasErrors(['body', 'url_or_path']);
});
