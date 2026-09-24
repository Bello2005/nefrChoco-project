<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EducationalContentController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\Admin\InteroperabilityController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PractitionerProfileController;
use App\Http\Controllers\Admin\SusReportController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin', 'throttle:zona-clinica'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
    Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->name('usuarios.edit');
    Route::put('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
    // Sin destroy: borrar una cuenta arrastraba lo que la persona registró. Se
    // desactiva, y la cuenta sigue existiendo como autora de sus registros.
    Route::patch('usuarios/{user}/desactivar', [UserController::class, 'deactivate'])->name('usuarios.desactivar');
    Route::patch('usuarios/{user}/reactivar', [UserController::class, 'reactivate'])->name('usuarios.reactivar');
    Route::put('usuarios/{user}/perfil-profesional', [PractitionerProfileController::class, 'update'])->name('usuarios.perfil-profesional');
    // La verificación la hace una persona en la consulta pública de ReTHUS.
    Route::post('usuarios/{user}/verificar-rethus', [PractitionerProfileController::class, 'verifyRethus'])->name('usuarios.verificar-rethus');

    // El padrón se consulta y edita desde la zona médica; aquí solo se custodia
    // y se elimina, que es lo que el rol médico no puede hacer.
    Route::get('pacientes', [PatientController::class, 'index'])->name('pacientes.index');
    Route::delete('pacientes/{patient}', [PatientController::class, 'destroy'])->name('pacientes.destroy');

    Route::get('educativo', [EducationalContentController::class, 'index'])->name('educativo.index');
    Route::get('educativo/crear', [EducationalContentController::class, 'create'])->name('educativo.create');
    Route::post('educativo', [EducationalContentController::class, 'store'])->name('educativo.store');
    Route::get('educativo/{educativo}/editar', [EducationalContentController::class, 'edit'])->name('educativo.edit');
    Route::put('educativo/{educativo}', [EducationalContentController::class, 'update'])->name('educativo.update');
    Route::delete('educativo/{educativo}', [EducationalContentController::class, 'destroy'])->name('educativo.destroy');

    Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');

    Route::get('catalogos', [CatalogController::class, 'index'])->name('catalogos.index');
    Route::get('institucion', [InstitutionController::class, 'index'])->name('institucion.index');
    Route::get('interoperabilidad', [InteroperabilityController::class, 'index'])->name('interoperabilidad.index');

    Route::get('usabilidad', [SusReportController::class, 'index'])->name('usabilidad.index');
});
