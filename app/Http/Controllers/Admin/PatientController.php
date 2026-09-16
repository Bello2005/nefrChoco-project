<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {}

    public function index(): Response
    {
        $patients = Patient::query()
            ->withCount(['clinicalHistories', 'appointments', 'vitalSigns'])
            ->orderBy('full_name')
            // Sin teléfono: va cifrado y esta pantalla es de custodia, no de contacto.
            ->get(['id', 'full_name', 'document_type', 'document_number', 'municipality']);

        return Inertia::render('admin/pacientes/index', [
            // Se muestra cuánto dato clínico cuelga de cada ficha para que
            // eliminar sea una decisión informada y no un clic a ciegas.
            'patients' => $patients->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'fullName' => $patient->full_name,
                'document' => $patient->document_type.' '.$patient->document_number,
                'municipality' => $patient->municipality,
                'clinicalHistories' => $patient->clinical_histories_count,
                'appointments' => $patient->appointments_count,
                'vitalSigns' => $patient->vital_signs_count,
            ]),
        ]);
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $this->patientService->delete($patient);

        return to_route('admin.pacientes.index')
            ->with('success', 'Ficha eliminada. Sus datos clínicos dejan de estar disponibles en la plataforma.');
    }
}
