<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Activación del doble factor, opcional y por usuario.
 *
 * Activar tiene dos pasos a propósito: primero se guarda el secreto y luego el
 * usuario demuestra que su aplicación genera códigos correctos. Recién ahí se
 * exige el segundo factor, para que nadie quede fuera de su cuenta por haber
 * escaneado mal el código QR.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $pendingSecret = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return Inertia::render('settings/two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pendingSecret,
            // El QR y el secreto solo viajan mientras la activación está en
            // curso; una vez confirmada no hay razón para volver a exponerlos.
            'qrCode' => $pendingSecret ? $this->twoFactor->qrCodeSvg($user, $user->two_factor_secret) : null,
            'secret' => $pendingSecret ? $user->two_factor_secret : null,
            'recoveryCodes' => $request->session()->get('recoveryCodes'),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return to_route('two-factor.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(
            ['code' => ['required', 'string', 'digits:6']],
            ['code.required' => 'Escribe el código que muestra tu aplicación.'],
            ['code' => 'código de verificación'],
        );

        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return to_route('two-factor.show');
        }

        if (! $this->twoFactor->verify($user->two_factor_secret, $request->string('code')->toString())) {
            return back()->withErrors([
                'code' => 'El código no coincide. Verifica que la hora de tu teléfono esté correcta.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        activity('seguridad')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->event('doble_factor_activado')
            ->log('Activó la autenticación de dos factores');

        // Los códigos de recuperación se muestran una sola vez, al confirmar.
        return to_route('two-factor.show')
            ->with('recoveryCodes', $user->two_factor_recovery_codes)
            ->with('success', 'Doble factor activado. Guarda tus códigos de recuperación en un lugar seguro.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
        ])->save();

        return to_route('two-factor.show')
            ->with('recoveryCodes', $user->two_factor_recovery_codes)
            ->with('success', 'Generamos códigos nuevos. Los anteriores dejaron de servir.');
    }

    public function disable(Request $request): RedirectResponse
    {
        // Se pide la contraseña: si alguien deja la sesión abierta, no debería
        // poder bajarle la seguridad a la cuenta con un solo clic.
        $request->validate(
            ['password' => ['required', 'current_password']],
            ['password.current_password' => 'La contraseña no es correcta.'],
        );

        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('seguridad')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->event('doble_factor_desactivado')
            ->log('Desactivó la autenticación de dos factores');

        return to_route('two-factor.show')->with('success', 'Doble factor desactivado.');
    }
}
