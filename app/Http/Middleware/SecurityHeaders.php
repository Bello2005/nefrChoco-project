<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de la respuesta.
 *
 * No hay Content-Security-Policy todavía a propósito: la teleconsulta carga el
 * script externo de Jitsi y la tipografía viene de un CDN, así que una CSP mal
 * ajustada rompería la videollamada en silencio. Queda pendiente para cuando
 * Jitsi esté autoalojado en el VPS y el origen sea propio.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Nadie debe poder incrustar la plataforma en un iframe ajeno y engañar
        // a un profesional para que actúe sobre una historia clínica.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Las URLs clínicas llevan el id del paciente: no se filtran a terceros.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Cámara y micrófono siguen habilitados para la sala de teleconsulta, que
        // se incrusta desde el dominio configurado de Jitsi. El resto se apaga.
        $jitsi = 'https://'.config('services.jitsi.domain');

        $response->headers->set('Permissions-Policy', implode(', ', [
            sprintf('camera=(self "%s")', $jitsi),
            sprintf('microphone=(self "%s")', $jitsi),
            sprintf('display-capture=(self "%s")', $jitsi),
            'geolocation=()',
            'payment=()',
            'usb=()',
        ]));

        return $response;
    }
}
