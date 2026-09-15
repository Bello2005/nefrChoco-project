<?php

use App\Http\Controllers\Paciente\AppointmentController;
use App\Http\Controllers\Paciente\ClinicalHistoryController;
use App\Http\Controllers\Paciente\ConsentController;
use App\Http\Controllers\Paciente\DashboardController;
use App\Http\Controllers\Paciente\EducationalContentController;
use App\Http\Controllers\Paciente\VitalSignController;
use App\Http\Middleware\EnsureDataConsent;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:paciente'])->prefix('paciente')->name('paciente.')->group(function () {
    // Fuera del muro de consentimiento: es la pantalla donde se otorga.
    Route::get('consentimiento', [ConsentController::class, 'show'])->name('consentimiento.show');
    Route::post('consentimiento', [ConsentController::class, 'store'])->name('consentimiento.store');

    Route::middleware(EnsureDataConsent::class)->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('mis-citas', [AppointmentController::class, 'index'])->name('mis-citas.index');

        Route::get('mi-historia-clinica', [ClinicalHistoryController::class, 'show'])->name('mi-historia-clinica.show');

        Route::get('signos-vitales', [VitalSignController::class, 'index'])->name('signos-vitales.index');
        Route::post('signos-vitales', [VitalSignController::class, 'store'])->name('signos-vitales.store');

        Route::get('educativo', [EducationalContentController::class, 'index'])->name('educativo.index');
    });
});
