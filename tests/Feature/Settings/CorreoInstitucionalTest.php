<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * El correo del personal es su identidad institucional: lo da la IPS y solo lo
 * cambia un administrador desde el panel de usuarios, donde se exige el
 * dominio. Desde ajustes solo lo cambia el paciente, que usa su correo
 * personal.
 */
test('un médico no puede cambiar su correo a un dominio externo desde ajustes', function () {
    $medico = User::factory()->create(['email' => 'ana.mosquera@nefrochoco.co']);
    $medico->assignRole('medico');

    $this->actingAs($medico)
        ->from('/settings/profile')
        ->patch('/settings/profile', [
            'name' => $medico->name,
            'email' => 'ana.mosquera@gmail.com',
        ])
        ->assertSessionHasErrors([
            'email' => 'Tu correo institucional solo lo puede cambiar un administrador.',
        ])
        ->assertRedirect('/settings/profile');

    expect($medico->refresh()->email)->toBe('ana.mosquera@nefrochoco.co');
});

/**
 * Es a propósito que ni siquiera se acepte otro correo del dominio: sin
 * verificación de correo, el personal podría apuntar su cuenta al buzón
 * institucional de otra persona y la recuperación de contraseña iría allá.
 */
test('el personal tampoco puede cambiar su correo por otro del dominio institucional', function (string $role) {
    $user = User::factory()->create(['email' => 'cuenta@nefrochoco.co']);
    $user->assignRole($role);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => 'otra.cuenta@nefrochoco.co',
    ])->assertSessionHasErrors('email');

    expect($user->refresh()->email)->toBe('cuenta@nefrochoco.co');
})->with(['medico', 'admin']);

test('el personal puede cambiar su nombre dejando el correo como está', function () {
    $medico = User::factory()->create(['email' => 'ana.mosquera@nefrochoco.co']);
    $medico->assignRole('medico');

    $this->actingAs($medico)->patch('/settings/profile', [
        'name' => 'Dra. Ana Mosquera Rentería',
        'email' => 'ana.mosquera@nefrochoco.co',
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    $medico->refresh();
    expect($medico->name)->toBe('Dra. Ana Mosquera Rentería');
    expect($medico->email_verified_at)->not->toBeNull();
});

test('un paciente sí puede cambiar su correo a uno de Gmail desde ajustes', function () {
    $paciente = User::factory()->create(['email' => 'juan.perea@hotmail.com']);
    $paciente->assignRole('paciente');

    $this->actingAs($paciente)->patch('/settings/profile', [
        'name' => $paciente->name,
        'email' => 'juan.perea@gmail.com',
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    expect($paciente->refresh()->email)->toBe('juan.perea@gmail.com');
});

test('la página de perfil solo deja editar el correo a quien puede cambiarlo', function (string $role, bool $canChangeEmail) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertInertia(fn ($page) => $page->where('canChangeEmail', $canChangeEmail));
})->with([
    'médico' => ['medico', false],
    'admin' => ['admin', false],
    'paciente' => ['paciente', true],
]);
