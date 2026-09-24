<?php

namespace App\Http\Controllers;

use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\PatientFormOptions;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Fichas por revisar": fichas cuya identidad no se pudo completar sin
 * adivinar (nombres separados con una heurística, tipo de documento o
 * municipio que no coincidió exacto con el catálogo, sexo biológico vacío).
 *
 * La usan el admin y el médico, porque el padrón es institucional. Muestra
 * solo datos de identidad, nada clínico.
 */
class PatientReviewController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly PatientFormOptions $formOptions,
    ) {}

    public function index(): Response
    {
        $patients = Patient::query()
            ->where('identity_review_pending', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'document_type', 'document_number', 'municipality', 'identity_review_reasons'])
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'fullName' => $patient->full_name,
                'document' => trim("{$patient->document_type} {$patient->document_number}"),
                'municipality' => $patient->municipality,
                'reasons' => $patient->identity_review_reasons ?? [],
            ]);

        return Inertia::render('fichas-por-revisar/index', [
            'patients' => $patients,
        ]);
    }

    public function edit(Patient $patient): Response
    {
        return Inertia::render('fichas-por-revisar/edit', [
            'patient' => $patient->only([
                'id', 'full_name', 'first_name', 'middle_name', 'first_surname', 'second_surname',
                'document_type', 'document_number', 'birth_date', 'biological_sex', 'municipality', 'municipality_code',
                'phone', 'emergency_contact_name', 'emergency_contact_phone',
                'gender_identity', 'ethnicity', 'disability', 'occupation', 'residence_zone', 'eapb_code', 'affiliation_type',
                'identity_review_reasons',
            ]),
            ...$this->formOptions->for($patient),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->patientService->update($patient, $request->validated());

        return to_route('fichas-por-revisar.index')->with('success', "Ficha de {$patient->full_name} completada.");
    }
}
