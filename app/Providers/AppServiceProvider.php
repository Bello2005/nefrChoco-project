<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Límite por usuario para las zonas autenticadas.
     *
     * El techo es alto a propósito: cuando vuelve la señal, la cola offline de
     * un paciente puede enviar decenas de mediciones seguidas, y ninguna puede
     * rebotar por límite de peticiones sin romper la sincronización. Sirve
     * contra abuso automatizado, no para racionar el uso normal.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('zona-clinica', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
