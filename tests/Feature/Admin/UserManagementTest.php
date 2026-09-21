<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function adminAutenticado(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('un admin puede crear un usuario médico desde el panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Dra. Ana Mosquera',
        'email' => 'ana.mosquera@nefrochoco.co',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'medico',
    ]);

    $response->assertRedirect(route('admin.usuarios.index'));

    $medico = User::where('email', 'ana.mosquera@nefrochoco.co')->first();
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

/**
 * El dominio institucional se exige al personal porque son las cuentas con
 * acceso a datos clínicos de terceros. Los pacientes quedan libres: su correo
 * personal es el único que revisan y por el que pueden recuperar la contraseña.
 */
test('el correo del personal fuera del dominio institucional se rechaza', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Dr. Carlos Rentería',
        'email' => 'carlos.renteria@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'medico',
    ])->assertSessionHasErrors([
        'email' => 'El personal debe usar un correo del dominio institucional (@nefrochoco.co).',
    ]);

    expect(User::where('email', 'carlos.renteria@gmail.com')->exists())->toBeFalse();
});

test('editar al personal tampoco permite sacarlo del dominio institucional', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $medico = User::factory()->create(['email' => 'ana.mosquera@nefrochoco.co']);
    $medico->assignRole('medico');

    $this->actingAs($admin)->put(route('admin.usuarios.update', $medico), [
        'name' => 'Dra. Ana Mosquera',
        'email' => 'ana.mosquera@gmail.com',
        'role' => 'medico',
    ])->assertSessionHasErrors('email');

    expect($medico->refresh()->email)->toBe('ana.mosquera@nefrochoco.co');
});

test('un paciente puede tener correo de Gmail', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->post(route('admin.usuarios.store'), [
        'name' => 'Juan Perea',
        'email' => 'juan.perea@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'paciente',
    ])->assertSessionHasNoErrors();

    $paciente = User::where('email', 'juan.perea@gmail.com')->first();
    expect($paciente)->not->toBeNull();
    expect($paciente->hasRole('paciente'))->toBeTrue();
});

/**
 * Vincular la cuenta con su ficha es lo que convierte un login vacío en un
 * paciente con seguimiento: sin patients.user_id el dashboard responde "tu
 * cuenta no está vinculada" y no hay citas, historia ni sala de teleconsulta.
 */
test('un paciente creado con ficha vinculada entra viendo su historia', function () {
    $ficha = Patient::factory()->create(['user_id' => null, 'full_name' => 'Rosalba Mosquera']);

    $this->actingAs(adminAutenticado())->post(route('admin.usuarios.store'), [
        'name' => 'Rosalba Mosquera',
        'email' => 'rosalba.mosquera@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'paciente',
        'patient_id' => $ficha->id,
    ])->assertSessionHasNoErrors();

    $cuenta = User::where('email', 'rosalba.mosquera@gmail.com')->sole();

    expect($ficha->refresh()->user_id)->toBe($cuenta->id);

    $this->actingAs($cuenta)
        ->get(route('paciente.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hasProfile', true)
            ->where('patientName', 'Rosalba Mosquera'));
});

test('el selector solo ofrece fichas sin cuenta, más la del usuario que se edita', function () {
    $libre = Patient::factory()->create(['user_id' => null, 'full_name' => 'Efraín Moreno']);

    $cuenta = User::factory()->create();
    $cuenta->assignRole('paciente');
    $propia = Patient::factory()->create(['user_id' => $cuenta->id, 'full_name' => 'Yined Asprilla']);

    $ajena = Patient::factory()->create(['user_id' => User::factory()->create()->id, 'full_name' => 'María Palacios']);

    $this->actingAs(adminAutenticado())
        ->get(route('admin.usuarios.edit', $cuenta))
        ->assertInertia(function ($page) use ($libre, $propia, $ajena) {
            $ofrecidas = collect($page->toArray()['props']['patients'])->pluck('id');

            expect($ofrecidas)->toContain($libre->id, $propia->id)
                ->not->toContain($ajena->id);
        });
});

test('no se puede vincular una ficha que ya es de otra cuenta', function () {
    $duenaOriginal = User::factory()->create();
    $ficha = Patient::factory()->create(['user_id' => $duenaOriginal->id]);

    $this->actingAs(adminAutenticado())->post(route('admin.usuarios.store'), [
        'name' => 'Cuenta Intrusa',
        'email' => 'intrusa@gmail.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'paciente',
        'patient_id' => $ficha->id,
    ])->assertSessionHasErrors('patient_id');

    expect($ficha->refresh()->user_id)->toBe($duenaOriginal->id);
});

test('cambiar de rol suelta la ficha que tenía la cuenta', function () {
    $cuenta = User::factory()->create(['email' => 'ana.mosquera@nefrochoco.co']);
    $cuenta->assignRole('paciente');
    $ficha = Patient::factory()->create(['user_id' => $cuenta->id]);

    $this->actingAs(adminAutenticado())->put(route('admin.usuarios.update', $cuenta), [
        'name' => 'Dra. Ana Mosquera',
        'email' => 'ana.mosquera@nefrochoco.co',
        'role' => 'medico',
    ])->assertSessionHasNoErrors();

    expect($ficha->refresh()->user_id)->toBeNull();
});

test('un usuario que no es admin no puede acceder al panel de usuarios', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $response = $this->actingAs($medico)->get(route('admin.usuarios.index'));

    $response->assertForbidden();
});
