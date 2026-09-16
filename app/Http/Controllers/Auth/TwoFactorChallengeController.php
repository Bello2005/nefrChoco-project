<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Segundo paso del ingreso cuando el usuario tiene doble factor activo.
 *
 * Entre la contraseña y el código no hay sesión iniciada: solo queda una marca
 * con el id pendiente, así que un desafío abandonado no deja a nadie dentro.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactor,
    ) {}

    public function create(): Response|RedirectResponse
    {
        if (! session()->has('login.id')) {
            return to_route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = User::find($request->session()->get('login.id'));

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return to_route('login');
        }

        $request->ensureIsNotRateLimited();

        if (! $this->passesChallenge($user, $request)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'code' => 'El código no es válido o ya expiró. Revisa la hora de tu teléfono e inténtalo otra vez.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        $remember = (bool) $request->session()->get('login.remember', false);
        $request->session()->forget(['login.id', 'login.remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function passesChallenge(User $user, TwoFactorChallengeRequest $request): bool
    {
        $code = $request->string('code')->toString();

        if ($code !== '' && $this->twoFactor->verify($user->two_factor_secret, $code)) {
            return true;
        }

        $recoveryCode = $request->string('recovery_code')->toString();

        return $recoveryCode !== '' && $this->twoFactor->consumeRecoveryCode($user, $recoveryCode);
    }
}
