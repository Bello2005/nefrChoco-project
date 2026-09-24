<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InteroperabilityReadiness;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Preparación para interoperar": qué datos faltan antes de generar RDA o
 * RIPS. Solo lectura, sin contenido clínico: nombres, qué campo falta y
 * dónde completarlo.
 */
class InteroperabilityController extends Controller
{
    public function index(InteroperabilityReadiness $readiness): Response
    {
        return Inertia::render('admin/interoperabilidad/index', $readiness->summary());
    }
}
