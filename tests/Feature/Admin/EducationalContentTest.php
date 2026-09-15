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
