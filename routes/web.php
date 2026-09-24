<?php

use App\Http\Controllers\CatalogSearchController;
use App\Http\Controllers\ClinicalHistoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientReviewController;
use App\Http\Controllers\SusController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $user = Auth::user();

        return match (true) {
            $user->hasRole('admin') => to_route('admin.dashboard'),
            $user->hasRole('medico') => to_route('medico.dashboard'),
            $user->hasRole('paciente') => to_route('paciente.dashboard'),
            default => Inertia::render('sin-rol'),
        };
    })->name('dashboard');

    Route::get('historias-clinicas/{clinicalHistory}', [ClinicalHistoryController::class, 'show'])
        ->name('historias-clinicas.show');

    Route::get('historias-clinicas/{clinicalHistory}/imprimir', [ClinicalHistoryController::class, 'print'])
        ->name('historias-clinicas.print');

    // Cuestionario de usabilidad: lo responde cualquier rol, porque la pregunta
    // es si la plataforma se deja usar, no si se usa bien un módulo concreto.
    Route::get('usabilidad', [SusController::class, 'create'])->name('usabilidad.create');
    Route::post('usabilidad', [SusController::class, 'store'])->name('usabilidad.store');

    Route::post('notificaciones/{notification}/leida', [NotificationController::class, 'markAsRead'])->name('notificaciones.read');
    Route::post('notificaciones/leidas', [NotificationController::class, 'markAllAsRead'])->name('notificaciones.read-all');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/medico.php';
require __DIR__.'/paciente.php';

// Buscador de catálogos oficiales para los formularios del personal.
Route::middleware(['auth', 'role:admin|medico', 'throttle:catalogos'])
    ->get('catalogos/{sistema}/buscar', CatalogSearchController::class)
    ->where('sistema', '[a-z0-9_]+')
    ->name('catalogos.buscar');

// Fichas por revisar (Res. 866 de 2021): admin y médico, porque el padrón es
// institucional. Solo datos de identidad.
Route::middleware(['auth', 'role:admin|medico', 'throttle:zona-clinica'])->group(function () {
    Route::get('fichas-por-revisar', [PatientReviewController::class, 'index'])->name('fichas-por-revisar.index');
    Route::get('fichas-por-revisar/{patient}/editar', [PatientReviewController::class, 'edit'])->name('fichas-por-revisar.edit');
    Route::put('fichas-por-revisar/{patient}', [PatientReviewController::class, 'update'])->name('fichas-por-revisar.update');
});
