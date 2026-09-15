<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un admin puede crear un usuario médico desde el panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Dra. Ana Mosquera',
        'email' => 'ana.mosquera@nefrochoco.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'medico',
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));

    $medico = User::where('email', 'ana.mosquera@nefrochoco.test')->first();
    expect($medico)->not->toBeNull();
    expect($medico->hasRole('medico'))->toBeTrue();
});

test('un admin puede crear un usuario paciente desde el panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Juan Perea',
        'email' => 'juan.perea@nefrochoco.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'paciente',
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));

    $paciente = User::where('email', 'juan.perea@nefrochoco.test')->first();
    expect($paciente)->not->toBeNull();
    expect($paciente->hasRole('paciente'))->toBeTrue();
});

test('un usuario que no es admin no puede acceder al panel de usuarios', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $response = $this->actingAs($medico)->get(route('admin.usuarios.index'));

    $response->assertForbidden();
});
