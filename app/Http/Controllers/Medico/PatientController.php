<?php

namespace App\Http\Controllers\Medico;

use App\Enums\BiologicalSex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Services\ClinicalAccessAuditor;
use App\Services\ClinicalDecisionSupport;
use App\Services\ClinicalFormService;
use App\Services\PatientService;
use App\Support\ClinicalRules\Recommendation;
use Carbon\CarbonImmutable;
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
        return Inertia::render('medico/pacientes/create', [
            'biologicalSexOptions' => BiologicalSex::options(),
        ]);
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
            'clinicalHistories' => fn ($query) => $query->with('author:id,name')->latest(),
            'appointments' => fn ($query) => $query->with('doctor:id,name')->latest('scheduled_at'),
            'clinicalForms' => fn ($query) => $query->latest()->limit(5),
            'vitalSigns' => fn ($query) => $query->latest('recorded_at')->limit(5),
        ]);

        return Inertia::render('medico/pacientes/show', [
            'patient' => $patient,
            'recommendations' => $this->clinicalDecisionSupport->forPatient($patient)
                ->map(fn (Recommendation $recommendation) => $recommendation->toArray()),
            'egfrSeries' => $this->egfrSeries($patient),
            'clinicalForms' => $patient->clinicalForms->map(fn ($form) => [
                'id' => $form->id,
                'templateName' => $form->templateName(),
                'riskLevel' => $form->risk_level,
                'score' => $form->score,
                // El seguimiento renal no puntúa: su resultado es la TFGe.
                'egfr' => $form->egfr,
                'kdigoG' => $form->kdigo_g,
                'kdigoA' => $form->kdigo_a,
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

    /**
     * Evolución de la TFGe del paciente.
     *
     * Consulta aparte porque la ficha solo carga los cinco formularios más
     * recientes, y la evolución renal necesita la serie completa.
     *
     * Se ordena por fecha de laboratorio y no por fecha de captura: un
     * resultado puede cargarse después de otro más reciente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function egfrSeries(Patient $patient): array
    {
        return $patient->clinicalForms()
            ->where('form_type', ClinicalFormService::RENAL_FORM)
            ->whereNotNull('egfr')
            ->get()
            ->sortBy(fn (ClinicalForm $form) => $form->answers['fecha_laboratorio'] ?? '')
            ->map(fn (ClinicalForm $form) => [
                'label' => CarbonImmutable::parse($form->answers['fecha_laboratorio'])->format('d/m/y'),
                'value' => (int) $form->egfr,
                'category' => $form->kdigo_g,
                'albuminuria' => $form->kdigo_a,
            ])
            ->values()
            ->all();
    }

    public function edit(Patient $patient): Response
    {
        return Inertia::render('medico/pacientes/edit', [
            'patient' => $patient,
            'biologicalSexOptions' => BiologicalSex::options(),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $this->patientService->update($patient, $request->validated());

        return to_route('medico.pacientes.show', $patient)->with('success', 'Paciente actualizado correctamente.');
    }
}
