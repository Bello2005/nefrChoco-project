<?php

namespace App\Http\Controllers;

use App\Models\ClinicalHistory;
use App\Models\ClinicalHistoryDiagnosis;
use App\Models\Teleconsultation;
use App\Models\TeleconsultationClarification;
use App\Services\AttentionRecordService;
use App\Services\Catalogs\CodeCatalog;
use App\Services\ClinicalAccessAuditor;
use App\Services\InstitutionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalHistoryController extends Controller
{
    public function __construct(
        private readonly ClinicalAccessAuditor $auditor,
    ) {}

    public function show(Request $request, ClinicalHistory $clinicalHistory): Response
    {
        Gate::authorize('view', $clinicalHistory);

        $clinicalHistory->load(['patient:id,full_name,municipality,document_type,document_number', 'author:id,name', 'diagnoses']);

        $this->auditor->recordHistoryAccess($clinicalHistory, $request);

        $attention = app(AttentionRecordService::class);

        // Las notas quedan en la teleconsulta, no en esta tabla: se anexan aquí
        // en lectura para que la historia sea el único lugar donde revisarlas.
        // Las aclaraciones y sus autores se cargan de una vez para no hacer una
        // consulta por nota.
        $teleconsultationNotes = Teleconsultation::query()
            ->whereNotNull('notes')
            ->whereHas('appointment', fn ($query) => $query->where('patient_id', $clinicalHistory->patient_id))
            ->with([
                'appointment' => fn ($query) => $query->select('id', 'doctor_id', 'scheduled_at')->with(['doctor:id,name', 'diagnoses.author:id,name']),
                'clarifications.author:id,name',
            ])
            ->latest('updated_at')
            ->get(['id', 'appointment_id', 'notes', 'status', 'updated_at'])
            ->map(function (Teleconsultation $teleconsultation) use ($request, $attention) {
                $appointment = $teleconsultation->appointment;
                // La política mira la teleconsulta desde la cita: se la entrega
                // ya cargada para no volver a pedirla.
                $appointment->setRelation('teleconsultation', $teleconsultation);

                return [
                    'id' => $teleconsultation->id,
                    'appointmentId' => $appointment->id,
                    'notes' => $teleconsultation->notes,
                    'scheduledAt' => $appointment->scheduled_at,
                    'doctorName' => $appointment->doctor?->name,
                    'canClarify' => $request->user()->can('clarifyTeleconsultation', $appointment),
                    // Vigentes y corregidos: una corrección no borra el original.
                    'diagnoses' => $attention->presentDiagnoses($appointment),
                    'clarifications' => $teleconsultation->clarifications->map(fn (TeleconsultationClarification $clarification) => [
                        'id' => $clarification->id,
                        'body' => $clarification->body,
                        'authorName' => $clarification->author->name,
                        'createdAt' => $clarification->created_at,
                    ]),
                ];
            });

        return Inertia::render('historias-clinicas/show', [
            'clinicalHistory' => $clinicalHistory,
            'historyDiagnoses' => $this->historyDiagnoses($clinicalHistory),
            'teleconsultationNotes' => $teleconsultationNotes,
            'hasCie10' => $attention->codingAvailable(),
        ]);
    }

    /**
     * Versión imprimible para expediente físico o envío a otra institución.
     *
     * Se entrega como HTML para imprimir desde el navegador en lugar de generar
     * un PDF en el servidor: evita una dependencia pesada y deja el archivo en
     * el dispositivo, sin subir datos clínicos a ningún servicio externo.
     */
    public function print(Request $request, ClinicalHistory $clinicalHistory): View
    {
        Gate::authorize('view', $clinicalHistory);

        $clinicalHistory->load(['patient', 'author:id,name', 'diagnoses']);

        $this->auditor->recordHistoryAccess($clinicalHistory, $request);

        return view('clinical-history-print', [
            'history' => $clinicalHistory,
            'patient' => $clinicalHistory->patient,
            'printedBy' => $request->user(),
            'diagnoses' => $this->historyDiagnoses($clinicalHistory),
            // Solo lo que esté configurado: nunca un valor de relleno.
            'institution' => app(InstitutionService::class)->data(),
        ]);
    }

    /** @return list<array{code: string, display: ?string}> */
    private function historyDiagnoses(ClinicalHistory $clinicalHistory): array
    {
        $catalog = app(CodeCatalog::class);

        return $clinicalHistory->diagnoses
            ->map(fn (ClinicalHistoryDiagnosis $diagnosis) => [
                'code' => $diagnosis->cie10_code,
                'display' => $catalog->display('cie10', $diagnosis->cie10_code),
            ])
            ->all();
    }
}
