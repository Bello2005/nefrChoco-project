<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de la respuesta.
 *
 * La Content-Security-Policy se salta en local a propósito: en `npm run dev`
 * los scripts y el socket de recarga en caliente de Vite viven en otro origen
 * (su propio puerto), y una CSP los bloquearía en silencio. En cualquier otro
 * entorno los assets ya están compilados y sirven desde el mismo origen que
 * la app, así que no hace falta esa excepción.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Se genera antes de renderizar para que @routes, en app.blade.php,
        // pueda ponerlo en su <script> inline: es el único script que no sale
        // de un archivo propio, así que es el único que necesita el nonce en
        // vez de quedar cubierto por 'self'.
        $nonce = base64_encode(random_bytes(16));
        View::share('cspNonce', $nonce);

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

        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($jitsi, $nonce));
        }

        return $response;
    }

    /**
     * Cada origen externo real de la app, y solo esos:
     * - $jitsi: script de arranque de la videollamada (script-src) y el iframe
     *   de la sala en sí (frame-src). Hoy es jitsi.bello.works, autoalojado
     *   pero sin JWT todavía — la CSP no sustituye esa autenticación
     *   pendiente, solo evita que la página cargue scripts o incruste salas
     *   de un origen que no sea el configurado.
     * - fonts.bunny.net: la hoja de estilos y los .woff2 de Plus Jakarta Sans
     *   (ver el <link> en app.blade.php).
     *
     * style-src lleva 'unsafe-inline' porque varios componentes (charts.tsx,
     * los degradados decorativos de welcome.tsx y auth-simple-layout.tsx, la
     * barra de aportes de usabilidad) usan `style={{ ... }}` con valores
     * calculados, no listas fijas de clases; y clinical-history-print.blade.php
     * trae su propio <style> para el PDF. Ningún script-src lleva
     * 'unsafe-inline': lo único inline ahí es el <script> de Ziggy, cubierto
     * por su propio nonce.
     */
    private function contentSecurityPolicy(string $jitsi, string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$jitsi}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-src {$jitsi}",
            "worker-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
