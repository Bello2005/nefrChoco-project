<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalForm\StoreClinicalFormRequest;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Services\ClinicalAccessAuditor;
use App\Services\ClinicalFormService;
use App\Support\ClinicalFormCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalFormController extends Controller
{
    public function __construct(
        private readonly ClinicalFormService $clinicalFormService,
        private readonly ClinicalAccessAuditor $auditor,
    ) {}

    public function index(Request $request): Response
    {
        $forms = ClinicalForm::query()
            ->with(['patient:id,full_name', 'recordedBy:id,name'])
            ->when($request->string('tipo')->toString(), fn ($query, $type) => $query->where('form_type', $type))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ClinicalForm $form) => [
                'id' => $form->id,
                'patient' => $form->patient?->full_name,
                'patientId' => $form->patient_id,
                'formType' => $form->form_type,
                'templateName' => $form->templateName(),
                'score' => $form->score,
                'riskLevel' => $form->risk_level,
                'recordedBy' => $form->recordedBy?->name,
                'createdAt' => $form->created_at->toIso8601String(),
            ]);

        return Inertia::render('medico/formularios-clinicos/index', [
            'forms' => $forms,
            'templates' => array_values(ClinicalFormCatalog::all()),
            'filters' => ['tipo' => $request->string('tipo')->toString()],
        ]);
    }

    public function create(Request $request): Response
    {
        $formType = $request->string('tipo')->toString();
        $template = ClinicalFormCatalog::find($formType);

        return Inertia::render('medico/formularios-clinicos/create', [
            'templates' => array_values(ClinicalFormCatalog::all()),
            'selectedTemplate' => $template,
            'patients' => Patient::orderBy('full_name')->get(['id', 'full_name']),
            'preselectedPatient' => $request->integer('paciente') ?: null,
        ]);
    }

    public function store(StoreClinicalFormRequest $request)
    {
        $patient = Patient::findOrFail($request->integer('patient_id'));

        $form = $this->clinicalFormService->create(
            $patient,
            Auth::user(),
            $request->string('form_type')->toString(),
            $request->array('answers'),
        );

        return to_route('medico.formularios-clinicos.show', $form)
            ->with('success', 'Formulario clínico registrado correctamente.');
    }

    public function show(Request $request, ClinicalForm $clinicalForm): Response
    {
        $clinicalForm->load(['patient:id,full_name,municipality', 'recordedBy:id,name']);

        $this->auditor->recordClinicalFormAccess($clinicalForm, $request);

        return Inertia::render('medico/formularios-clinicos/show', [
            'form' => [
                'id' => $clinicalForm->id,
                'templateName' => $clinicalForm->templateName(),
                'formType' => $clinicalForm->form_type,
                'patient' => $clinicalForm->patient,
                'recordedBy' => $clinicalForm->recordedBy?->name,
                'createdAt' => $clinicalForm->created_at->toIso8601String(),
                'answers' => $this->clinicalFormService->readableAnswers($clinicalForm),
            ],
            'score' => ClinicalFormCatalog::score($clinicalForm->form_type, $clinicalForm->answers ?? []),
        ]);
    }
}
