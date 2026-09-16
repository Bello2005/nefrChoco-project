<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EducationalContentController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
    Route::get('usuarios/crear', [UserController::class, 'create'])->name('usuarios.create');
    Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
    Route::get('usuarios/{user}/editar', [UserController::class, 'edit'])->name('usuarios.edit');
    Route::put('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
    Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->name('usuarios.destroy');

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
});
