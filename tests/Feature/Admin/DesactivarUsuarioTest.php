<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
});

test('el admin desactiva una cuenta y esa persona ya no puede iniciar sesión', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.desactivar', $this->medico))
        ->assertRedirect(route('admin.usuarios.index'));

    expect($this->medico->fresh()->isDeactivated())->toBeTrue();

    auth()->logout();

    $this->post(route('login'), ['email' => $this->medico->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);

    $this->assertGuest();
});

test('con la contraseña incorrecta una cuenta desactivada ve el mensaje de siempre', function () {
    $desactivado = User::factory()->deactivated()->create();

    // Si viera "cuenta desactivada" sin saber la contraseña, el mensaje
    // serviría para averiguar qué correos existen en la plataforma.
    $this->post(route('login'), ['email' => $desactivado->email, 'password' => 'otra-clave'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('una sesión abierta de una cuenta desactivada se cierra en la siguiente petición', function () {
    $this->actingAs($this->medico)->get(route('medico.dashboard'))->assertOk();

    $this->medico->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('medico.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.deactivated')]);

    $this->assertGuest();
});

test('el admin reactiva una cuenta y esa persona vuelve a entrar', function () {
    $desactivado = User::factory()->deactivated()->create();
    $desactivado->assignRole('medico');

    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.reactivar', $desactivado))
        ->assertRedirect(route('admin.usuarios.index'));

    expect($desactivado->fresh()->isDeactivated())->toBeFalse();

    auth()->logout();

    $this->post(route('login'), ['email' => $desactivado->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($desactivado);
});

test('el admin no puede desactivarse a sí mismo', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.desactivar', $this->admin))
        ->assertRedirect(route('admin.usuarios.index'))
        ->assertSessionHas('error');

    expect($this->admin->fresh()->isDeactivated())->toBeFalse();
});

test('un médico no puede desactivar cuentas', function () {
    $otro = User::factory()->create();

    $this->actingAs($this->medico)
        ->patch(route('admin.usuarios.desactivar', $otro))
        ->assertForbidden();

    expect($otro->fresh()->isDeactivated())->toBeFalse();
});

test('desactivar a un médico conserva sus citas y sus notas', function () {
    $cita = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_COMPLETED,
    ]);
    $teleconsulta = Teleconsultation::factory()->create([
        'appointment_id' => $cita->id,
        'status' => Teleconsultation::STATUS_FINISHED,
        'notes' => 'Nota firmada por el médico.',
    ]);

    $this->actingAs($this->admin)->patch(route('admin.usuarios.desactivar', $this->medico));

    expect($cita->fresh())->not->toBeNull();
    expect($cita->fresh()->doctor->name)->toBe($this->medico->name);
    expect($teleconsulta->fresh()->notes)->toBe('Nota firmada por el médico.');
});

test('desactivar y reactivar dejan rastro en la auditoría', function () {
    $this->actingAs($this->admin)->patch(route('admin.usuarios.desactivar', $this->medico));
    $this->actingAs($this->admin)->patch(route('admin.usuarios.reactivar', $this->medico));

    foreach (['deactivated', 'reactivated'] as $evento) {
        $registro = Activity::where('event', $evento)->latest('id')->first();

        expect($registro)->not->toBeNull();
        expect($registro->causer_id)->toBe($this->admin->id);
        expect($registro->subject_type)->toBe(User::class);
        expect($registro->subject_id)->toBe($this->medico->id);
    }
});

test('la zona de administración ya no expone una ruta para borrar cuentas', function () {
    expect(Route::has('admin.usuarios.destroy'))->toBeFalse();
});

test('un paciente ve en Ajustes a dónde pedir la eliminación de sus datos y el personal no', function () {
    $paciente = User::factory()->create();
    $paciente->assignRole('paciente');

    $this->actingAs($paciente)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('dataRequestEmail', config('privacy.contact_email')));

    $this->actingAs($this->medico)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('dataRequestEmail', null));
});
