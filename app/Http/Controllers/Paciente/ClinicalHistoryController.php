<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalHistoryController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $patient = Auth::user()->patient;
        $clinicalHistory = $patient?->latestClinicalHistory;

        if (! $clinicalHistory) {
            return Inertia::render('paciente/historia-clinica/vacio');
        }

        return to_route('historias-clinicas.show', $clinicalHistory);
    }
}
