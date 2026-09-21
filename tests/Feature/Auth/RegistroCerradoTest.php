<?php

use App\Models\User;

/**
 * El registro autoservicio está cerrado: las cuentas las crea el administrador
 * desde /admin/usuarios y ahí se les asigna el rol. El formulario abierto creaba
 * usuarios sin rol, que quedaban dentro de la plataforma sin nada que ver.
 */
test('la pantalla de registro ya no existe', function () {
    $this->get('/register')->assertNotFound();
});

test('el registro por POST ya no existe y no crea cuentas', function () {
    $this->post('/register', [
        'name' => 'Cuenta sin rol',
        'email' => 'sin-rol@nefrochoco.co',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'sin-rol@nefrochoco.co']);
});

test('una cuenta sin rol ve el aviso y no el placeholder', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('sin-rol'));
});
