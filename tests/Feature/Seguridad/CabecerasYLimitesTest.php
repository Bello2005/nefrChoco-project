<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

test('el login lleva una Content-Security-Policy que permite el script y el iframe de Jitsi', function () {
    $response = $this->get('/login');

    $response->assertOk();

    $csp = $response->headers->get('Content-Security-Policy');
    $jitsi = 'https://'.config('services.jitsi.domain');

    expect($csp)->not->toBeNull();
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("script-src 'self' 'nonce-");
    expect($csp)->toContain($jitsi);
    expect($csp)->toContain("frame-src {$jitsi}");
    expect($csp)->toContain('https://fonts.bunny.net');
    expect($csp)->toContain("frame-ancestors 'none'");
    expect($csp)->toContain("object-src 'none'");
});

test('el nonce de la CSP es el mismo que lleva el script inline de Ziggy', function () {
    $response = $this->get('/login');

    $csp = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $match);

    expect($match)->not->toBeEmpty();

    $response->assertSee(sprintf('nonce="%s"', $match[1]), false);
});

test('en local no se manda Content-Security-Policy, porque rompería la recarga en caliente de Vite', function () {
    app()->instance('env', 'local');

    $response = $this->get('/login');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
});

test('una teleconsulta real carga con la CSP puesta y sin que se rompa la sala', function () {
    $this->seed(RoleSeeder::class);

    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $appointment = Appointment::factory()->create([
        'doctor_id' => $medico->id,
        'patient_id' => Patient::factory(),
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_SCHEDULED,
        'scheduled_at' => now(),
    ]);

    $response = $this->actingAs($medico)->get(route('medico.citas.teleconsulta', $appointment));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('medico/teleconsulta/show'));

    $csp = $response->headers->get('Content-Security-Policy');
    $jitsi = 'https://'.config('services.jitsi.domain');

    expect($csp)->not->toBeNull();
    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->toContain($jitsi)
        ->toContain("frame-src {$jitsi}");
});
