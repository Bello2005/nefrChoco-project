<?php

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

function activarDobleFactor(User $user): string
{
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

function codigoValido(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

test('quien no tiene doble factor entra con solo su contraseña', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('con doble factor activo la contraseña sola no inicia sesión', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    // Lo importante: la contraseña correcta NO dejó la sesión abierta.
    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('el código correcto completa el ingreso', function () {
    $user = User::factory()->create();
    $secret = activarDobleFactor($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.login'), ['code' => codigoValido($secret)])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('un código equivocado no deja entrar', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.login'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('un código de recuperación permite entrar y se consume', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);

    $codigo = $user->fresh()->two_factor_recovery_codes[0];

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login'), ['recovery_code' => $codigo])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_recovery_codes)->not->toContain($codigo);
});

test('un código de recuperación ya usado no sirve una segunda vez', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);
    $codigo = $user->fresh()->two_factor_recovery_codes[0];

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login'), ['recovery_code' => $codigo]);
    $this->post('/logout');

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login'), ['recovery_code' => $codigo])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('el desafío no se puede abrir sin haber pasado la contraseña', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
});

test('activar deja el secreto guardado pero todavía sin exigirse', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('two-factor.enable'))->assertRedirect(route('two-factor.show'));

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
    // Sin confirmar no se exige: nadie queda fuera por abandonar el proceso.
    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

test('confirmar con el código correcto activa el doble factor', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('two-factor.enable'));

    $secret = $user->fresh()->two_factor_secret;

    $this->actingAs($user)
        ->post(route('two-factor.confirm'), ['code' => codigoValido($secret)])
        ->assertRedirect(route('two-factor.show'))
        ->assertSessionHas('recoveryCodes');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('confirmar con un código equivocado no activa nada', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('two-factor.enable'));

    $this->actingAs($user)
        ->post(route('two-factor.confirm'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('desactivar exige la contraseña correcta', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);

    $this->actingAs($user)
        ->delete(route('two-factor.disable'), ['password' => 'contraseña-incorrecta'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('two-factor.disable'), ['password' => 'password'])
        ->assertRedirect(route('two-factor.show'));

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
    expect($user->fresh()->two_factor_secret)->toBeNull();
});

test('el secreto y los códigos de recuperación quedan cifrados en la base', function () {
    $user = User::factory()->create();
    $secret = activarDobleFactor($user);

    $crudo = DB::table('users')->where('id', $user->id)->first();

    expect($crudo->two_factor_secret)->not->toBe($secret);
    expect($crudo->two_factor_secret)->not->toContain($secret);
    expect($crudo->two_factor_recovery_codes)->not->toContain($user->two_factor_recovery_codes[0]);
});

test('regenerar los códigos de recuperación invalida los anteriores', function () {
    $user = User::factory()->create();
    activarDobleFactor($user);
    $anteriores = $user->fresh()->two_factor_recovery_codes;

    $this->actingAs($user)->post(route('two-factor.recovery-codes'))->assertRedirect(route('two-factor.show'));

    $nuevos = $user->fresh()->two_factor_recovery_codes;

    expect($nuevos)->toHaveCount(8);
    expect(array_intersect($anteriores, $nuevos))->toBeEmpty();
});
