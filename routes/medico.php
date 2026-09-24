<?php

use App\Http\Controllers\Medico\AppointmentController;
use App\Http\Controllers\Medico\ClinicalFormController;
use App\Http\Controllers\Medico\ClinicalHistoryController;
use App\Http\Controllers\Medico\DashboardController;
use App\Http\Controllers\Medico\PatientController;
use App\Http\Controllers\Medico\TeleconsultationClarificationController;
use App\Http\Controllers\Medico\TeleconsultationController;
use App\Http\Controllers\Medico\VitalSignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:medico', 'throttle:zona-clinica'])->prefix('medico')->name('medico.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Sin destroy: eliminar una ficha arrastra su historia clínica y queda en
    // administración, que responde por la custodia del dato (Ley 1581 de 2012).
    Route::resource('pacientes', PatientController::class)
        ->parameters(['pacientes' => 'patient'])
        ->except(['destroy']);

    Route::get('pacientes/{patient}/historia-clinica/crear', [ClinicalHistoryController::class, 'create'])->name('pacientes.historia-clinica.create');
    Route::post('pacientes/{patient}/historia-clinica', [ClinicalHistoryController::class, 'store'])->name('pacientes.historia-clinica.store');

    // Sin destroy: una cita atendida es parte del registro clínico y arrastraba
    // sus notas de teleconsulta. La que ya no va se cancela desde Editar.
    Route::resource('citas', AppointmentController::class)
        ->parameters(['citas' => 'appointment'])
        ->except(['show', 'destroy']);

    Route::get('citas/{appointment}/teleconsulta', [TeleconsultationController::class, 'show'])->name('citas.teleconsulta');
    Route::post('citas/{appointment}/teleconsulta/cerrar', [TeleconsultationController::class, 'complete'])->name('citas.teleconsulta.complete');
    // Solo crear: una aclaración, como la nota que aclara, no se edita ni se borra.
    Route::post('citas/{appointment}/teleconsulta/aclaraciones', [TeleconsultationClarificationController::class, 'store'])->name('citas.teleconsulta.aclaraciones.store');

    Route::get('formularios-clinicos', [ClinicalFormController::class, 'index'])->name('formularios-clinicos.index');
    Route::get('formularios-clinicos/crear', [ClinicalFormController::class, 'create'])->name('formularios-clinicos.create');
    Route::post('formularios-clinicos', [ClinicalFormController::class, 'store'])->name('formularios-clinicos.store');
    Route::get('formularios-clinicos/{clinicalForm}', [ClinicalFormController::class, 'show'])->name('formularios-clinicos.show');

    Route::get('telemonitoreo', [VitalSignController::class, 'index'])->name('telemonitoreo.index');
    Route::get('telemonitoreo/{patient}', [VitalSignController::class, 'show'])->name('telemonitoreo.show');
    Route::post('telemonitoreo/{patient}', [VitalSignController::class, 'store'])->name('telemonitoreo.store');
});
