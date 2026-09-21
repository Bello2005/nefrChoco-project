<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('en local o testing el admin se sigue creando con la contraseña de la demo', function () {
    (new AdminUserSeeder)->run();

    $admin = User::where('email', 'admin@nefrochoco.co')->firstOrFail();

    expect(Hash::check('password', $admin->password))->toBeTrue();
    expect($admin->hasRole('admin'))->toBeTrue();
});

test('fuera de local o testing, sin ADMIN_INITIAL_PASSWORD, el seeder falla en vez de usar la contraseña de la demo', function () {
    app()->instance('env', 'production');
    config(['services.admin.initial_password' => null]);

    (new AdminUserSeeder)->run();
})->throws(RuntimeException::class, 'ADMIN_INITIAL_PASSWORD');

test('fuera de local o testing, con ADMIN_INITIAL_PASSWORD definida, el admin se crea con esa contraseña', function () {
    app()->instance('env', 'production');
    config(['services.admin.initial_password' => 'una-clave-robusta-de-produccion']);

    (new AdminUserSeeder)->run();

    $admin = User::where('email', 'admin@nefrochoco.co')->firstOrFail();

    expect(Hash::check('una-clave-robusta-de-produccion', $admin->password))->toBeTrue();
    expect(Hash::check('password', $admin->password))->toBeFalse();
});
