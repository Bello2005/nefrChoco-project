<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige la autorización del titular antes de mostrarle sus datos clínicos.
 *
 * Solo aplica al paciente: el personal de la IPS trata los datos por su
 * relación laboral con la institución, mientras que el titular debe autorizar
 * expresamente el tratamiento (Ley 1581 de 2012).
 */
class EnsureDataConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $patient = $request->user()?->patient;

        // Una cuenta sin ficha vinculada todavía no tiene datos que autorizar;
        // la propia pantalla se lo explica en lugar de dejarla en un bucle.
        if ($patient && ! $patient->hasCurrentConsent()) {
            return to_route('paciente.consentimiento.show');
        }

        return $next($request);
    }
}
