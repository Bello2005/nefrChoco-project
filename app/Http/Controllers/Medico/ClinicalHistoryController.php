<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalHistory\StoreClinicalHistoryRequest;
use App\Models\Patient;
use App\Services\ClinicalHistoryService;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalHistoryController extends Controller
{
    public function __construct(
        private readonly ClinicalHistoryService $clinicalHistoryService,
    ) {}

    public function create(Patient $patient): Response
    {
        return Inertia::render('medico/pacientes/historia-clinica/create', [
            'patient' => $patient->only(['id', 'full_name']),
        ]);
    }

    public function store(StoreClinicalHistoryRequest $request, Patient $patient)
    {
        $this->clinicalHistoryService->create($patient, $request->user(), $request->validated());

        return to_route('medico.pacientes.show', $patient)->with('success', 'Historia clínica registrada correctamente.');
    }
}
