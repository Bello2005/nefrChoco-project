<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * Sin lang/es/validation.php el validador no encuentra la línea y devuelve la
 * clave cruda ("validation.confirmed"), que es lo que acababa viéndose en el
 * formulario. La prueba fija el mensaje ya traducido, no solo que no sea la clave.
 */
test('la confirmación de contraseña que no coincide responde en español', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'una-contrasena-nueva',
            'password_confirmation' => 'otra-distinta',
        ])->assertSessionHasErrors([
            'password' => 'La confirmación de contraseña no coincide.',
        ]);

        return true;
    });
});
