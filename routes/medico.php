<?php

use App\Http\Controllers\Medico\AppointmentController;
use App\Http\Controllers\Medico\ClinicalFormController;
use App\Http\Controllers\Medico\ClinicalHistoryController;
use App\Http\Controllers\Medico\DashboardController;
use App\Http\Controllers\Medico\PatientController;
use App\Http\Controllers\Medico\TeleconsultationController;
use App\Http\Controllers\Medico\VitalSignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:medico'])->prefix('medico')->name('medico.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('pacientes', PatientController::class)->parameters(['pacientes' => 'patient']);

    Route::get('pacientes/{patient}/historia-clinica/crear', [ClinicalHistoryController::class, 'create'])->name('pacientes.historia-clinica.create');
    Route::post('pacientes/{patient}/historia-clinica', [ClinicalHistoryController::class, 'store'])->name('pacientes.historia-clinica.store');

    Route::resource('citas', AppointmentController::class)
        ->parameters(['citas' => 'appointment'])
        ->except(['show']);

    Route::get('citas/{appointment}/teleconsulta', [TeleconsultationController::class, 'show'])->name('citas.teleconsulta');
    Route::post('citas/{appointment}/teleconsulta/cerrar', [TeleconsultationController::class, 'complete'])->name('citas.teleconsulta.complete');

    Route::get('formularios-clinicos', [ClinicalFormController::class, 'index'])->name('formularios-clinicos.index');
    Route::get('formularios-clinicos/crear', [ClinicalFormController::class, 'create'])->name('formularios-clinicos.create');
    Route::post('formularios-clinicos', [ClinicalFormController::class, 'store'])->name('formularios-clinicos.store');
    Route::get('formularios-clinicos/{clinicalForm}', [ClinicalFormController::class, 'show'])->name('formularios-clinicos.show');

    Route::get('telemonitoreo', [VitalSignController::class, 'index'])->name('telemonitoreo.index');
    Route::get('telemonitoreo/{patient}', [VitalSignController::class, 'show'])->name('telemonitoreo.show');
    Route::post('telemonitoreo/{patient}', [VitalSignController::class, 'store'])->name('telemonitoreo.store');
});
