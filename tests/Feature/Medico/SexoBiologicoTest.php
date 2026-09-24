<?php

use App\Enums\BiologicalSex;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Sexo biológico en la ficha del paciente.
 *
 * Se pide solo porque CKD-EPI 2021 lo usa como variable. Es obligatorio de
 * ahora en adelante, pero las fichas anteriores quedaron sin el dato y no se
 * puede inventar: la ficha avisa y el cálculo renal se bloquea hasta que
 * alguien lo complete.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

function fichaNueva(array $extra = []): array
{
    return array_merge([
        'first_name' => 'Rosalba',
        'first_surname' => 'Mosquera',
        'document_type' => 'CC',
        'document_number' => '1077445566',
        'birth_date' => '1955-03-21',
        'biological_sex' => 'femenino',
        'municipality' => 'Condoto',
        'phone' => '3001234567',
    ], $extra);
}

test('registrar una ficha sin sexo biológico falla la validación', function () {
    $datos = fichaNueva();
    unset($datos['biological_sex']);

    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.store'), $datos)
        ->assertSessionHasErrors('biological_sex');

    expect(Patient::count())->toBe(0);
});

test('solo se admiten los valores del catálogo de sexo biológico del IHCE', function (string $valor) {
    $this->actingAs($this->medico)
        ->post(route('medico.pacientes.store'), fichaNueva(['biological_sex' => $valor]))
        ->assertSessionHasErrors('biological_sex');

    expect(Patient::count())->toBe(0);
})->with([
    'otro' => 'otro',
    'vacío' => '',
    'no binario' => 'no_binario',
]);

test('registrar una ficha con sexo biológico válido la guarda', function () {
    $this->actingAs($this->medico)->post(route('medico.pacientes.store'), fichaNueva());

    expect(Patient::first()->biological_sex)->toBe(BiologicalSex::Female);
});

test('editar una ficha también exige el sexo biológico', function () {
    $patient = Patient::factory()->create();

    $datos = fichaNueva(['document_number' => $patient->document_number]);
    unset($datos['biological_sex']);

    $this->actingAs($this->medico)
        ->put(route('medico.pacientes.update', $patient), $datos)
        ->assertSessionHasErrors('biological_sex');
});

test('editar una ficha antigua es la vía para completar el dato', function () {
    $patient = Patient::factory()->withoutBiologicalSex()->create();

    expect($patient->biological_sex)->toBeNull();

    $this->actingAs($this->medico)->put(
        route('medico.pacientes.update', $patient),
        fichaNueva(['document_number' => $patient->document_number, 'biological_sex' => 'masculino']),
    );

    expect($patient->fresh()->biological_sex)->toBe(BiologicalSex::Male);
});

test('una ficha antigua sin el dato no rompe la pantalla, solo lo deja visible', function () {
    $patient = Patient::factory()->withoutBiologicalSex()->create();

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('medico/pacientes/show')
            ->where('patient.biological_sex', null)
        );
});

test('el formulario recibe las opciones desde el enum, no fijas en el frontend', function () {
    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('medico/pacientes/create')
            ->has('biologicalSexOptions', 4)
            ->where('biologicalSexOptions.0.value', BiologicalSex::Female->value)
        );
});
