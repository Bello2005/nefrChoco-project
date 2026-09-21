<?php

use App\Models\SusResponse;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** @param  array<int, int>  $answers */
function enviarSus(User $user, array $answers, ?string $comments = null)
{
    return test()->actingAs($user)->post(route('usabilidad.store'), [
        'answers' => array_combine(range(1, 10), $answers),
        'comments' => $comments,
    ]);
}

test('el cuestionario guarda el puntaje calculado para un set de respuestas conocido', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    // Impares: 4,4,4,4,4 → 3 cada uno = 15. Pares: 2,2,2,3,2 → 3,3,3,2,3 = 14.
    // Suma 29 × 2.5 = 72.5.
    enviarSus($medico, [4, 2, 4, 2, 4, 2, 4, 3, 4, 2])->assertSessionHasNoErrors();

    $response = SusResponse::sole();

    expect($response->score)->toBe(72.5);
    expect($response->role)->toBe('medico');
    expect($response->answers)->toBe(array_combine(range(1, 10), [4, 2, 4, 2, 4, 2, 4, 3, 4, 2]));
});

test('el cuestionario exige los diez ítems', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $this->actingAs($paciente)->post(route('usabilidad.store'), [
        'answers' => [1 => 4, 2 => 2, 3 => 4],
    ])->assertSessionHasErrors(['answers']);

    expect(SusResponse::count())->toBe(0);
});

test('una respuesta fuera de la escala 1 a 5 es rechazada', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    enviarSus($paciente, [4, 2, 4, 2, 9, 2, 4, 3, 4, 2])->assertSessionHasErrors('answers.5');

    expect(SusResponse::count())->toBe(0);
});

test('una misma persona no puede responder dos veces', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    enviarSus($paciente, [5, 1, 5, 1, 5, 1, 5, 1, 5, 1])->assertSessionHasNoErrors();
    enviarSus($paciente, [1, 5, 1, 5, 1, 5, 1, 5, 1, 5]);

    expect(SusResponse::count())->toBe(1);
    expect(SusResponse::sole()->score)->toBe(100.0);
});

test('el reporte promedia por rol y no expone quién respondió', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');
    enviarSus($medico, [5, 1, 5, 1, 5, 1, 5, 1, 5, 1], 'Muy cómoda de usar.');

    $paciente = User::factory()->create(['name' => 'Juan Perea']);
    $paciente->assignRole('paciente');
    enviarSus($paciente, [4, 2, 4, 2, 4, 2, 4, 3, 4, 2]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.usabilidad.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/usabilidad/index')
        ->where('total', 2)
        // (100 + 72.5) / 2
        ->where('average', 86.3)
        ->has('byRole', 2)
        ->has('items', 10)
        ->has('comments', 1)
    );

    // El nombre de quien respondió no viaja al reporte.
    expect($response->getContent())->not->toContain('Juan Perea');
});

test('un médico no puede ver el reporte de usabilidad', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($medico)->get(route('admin.usabilidad.index'))->assertForbidden();
});
