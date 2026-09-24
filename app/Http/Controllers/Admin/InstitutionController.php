<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InstitutionService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Datos de la institución": solo lectura. Los valores viven en el .env del
 * servidor (INSTITUTION_*); la pantalla muestra cuáles faltan.
 */
class InstitutionController extends Controller
{
    public function index(InstitutionService $institution): Response
    {
        return Inertia::render('admin/institucion/index', [
            'fields' => $institution->fields(),
            'missing' => $institution->missing(),
        ]);
    }
}
