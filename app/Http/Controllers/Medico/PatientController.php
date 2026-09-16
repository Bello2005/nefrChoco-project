<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\Patient;
use App\Services\ClinicalAccessAuditor;
use App\Services\ClinicalDecisionSupport;
use App\Services\PatientService;
use App\Support\ClinicalRules\Recommendation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly ClinicalDecisionSupport $clinicalDecisionSupport,
        private readonly ClinicalAccessAuditor $auditor,
    ) {}

    public function index(): Response
    {
        $patients = Patient::query()
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'document_type', 'document_number', 'municipality', 'phone']);

        return Inertia::render('medico/pacientes/index', [
            'patients' => $patients,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('medico/pacientes/create');
    }

    public function store(StorePatientRequest $request)
    {
        $patient = $this->patientService->create($request->validated());

        return to_route('medico.pacientes.show', $patient)->with('success', 'Paciente registrado correctamente.');
    }

    public function show(Request $request, Patient $patient): Response
    {
        // El acceso lo concede el middleware de rol: el padrón es institucional
        // y no hay policy por recurso. Aun así la lectura queda registrada,
        // porque esta pantalla arrastra historias, formularios y mediciones.
        $this->auditor->recordPatientFileAccess($patient, $request);

        $patient->load([
            'clinicalHistories' => fn ($query) => $query->latest(),
            'appointments' => fn ($query) => $query->with('doctor:id,name')->latest('scheduled_at'),
            'clinicalForms' => fn ($query) => $query->latest()->limit(5),
            'vitalSigns' => fn ($query) => $query->latest('recorded_at')->limit(5),
        ]);

        return Inertia::render('medico/pacientes/show', [
            'patient' => $patient,
            'recommendations' => $this->clinicalDecisionSupport->forPatient($patient)
                ->map(fn (Recommendation $recommendation) => $recommendation->toArray()),
            'clinicalForms' => $patient->clinicalForms->map(fn ($form) => [
                'id' => $form->id,
                'templateName' => $form->templateName(),
                'riskLevel' => $form->risk_level,
                'score' => $form->score,
                'createdAt' => $form->created_at->toIso8601String(),
            ]),
            'vitalSigns' => $patient->vitalSigns->map(fn ($sign) => [
                'id' => $sign->id,
                'label' => $sign->typeEnum()?->shortLabel() ?? $sign->type,
                'value' => (float) $sign->value,
                'unit' => $sign->unit,
                'status' => $sign->status(),
                'recordedAt' => $sign->recorded_at->toIso8601String(),
            ]),
        ]);
    }

    public function edit(Patient $patient): Response
    {
        return Inertia::render('medico/pacientes/edit', [
            'patient' => $patient,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $this->patientService->update($patient, $request->validated());

        return to_route('medico.pacientes.show', $patient)->with('success', 'Paciente actualizado correctamente.');
    }
}
