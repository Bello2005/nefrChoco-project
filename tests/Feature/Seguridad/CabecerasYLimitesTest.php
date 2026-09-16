<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

test('las respuestas llevan las cabeceras de seguridad básicas', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('la política de permisos deja pasar cámara y micrófono al dominio de Jitsi', function () {
    $policy = $this->get('/login')->headers->get('Permissions-Policy');

    // Si esta cabecera se rompe, el navegador deja de conceder cámara y
    // micrófono dentro del iframe y la teleconsulta queda muda sin mostrar
    // ningún error: es justo el fallo que una prueba de humo no ve.
    $jitsi = 'https://'.config('services.jitsi.domain');

    expect($policy)->toContain(sprintf('camera=(self "%s")', $jitsi));
    expect($policy)->toContain(sprintf('microphone=(self "%s")', $jitsi));
    expect($policy)->toContain('geolocation=()');
});

test('el límite por usuario alcanza para vaciar la cola offline de una sola vez', function () {
    $limit = (RateLimiter::limiter('zona-clinica'))(Request::create('/'));

    expect($limit)->toBeInstanceOf(Limit::class);
    // Al volver la señal, la cola de un paciente puede enviar decenas de
    // mediciones seguidas; ninguna puede rebotar por límite de peticiones.
    expect($limit->maxAttempts)->toBeGreaterThanOrEqual(120);
});

test('las tres zonas autenticadas están limitadas por peticiones', function (string $routeName) {
    $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

    expect($middleware)->toContain('throttle:zona-clinica');
})->with([
    'medico.dashboard',
    'paciente.dashboard',
    'admin.dashboard',
]);
