<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca de la plataforma a una cuenta que se desactivó con la sesión abierta.
 *
 * El login ya rechaza las cuentas desactivadas, pero no alcanza: quien tenía
 * una sesión abierta o marcó "recordarme" seguiría entrando hasta que expire.
 * Se revisa en cada petición del grupo web para que desactivar surta efecto
 * de inmediato.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isDeactivated()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.deactivated')]);
        }

        return $next($request);
    }
}
