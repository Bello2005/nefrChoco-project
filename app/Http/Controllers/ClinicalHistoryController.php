<?php

namespace App\Http\Controllers;

use App\Models\ClinicalHistory;
use App\Models\Teleconsultation;
use App\Services\ClinicalAccessAuditor;
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

        $clinicalHistory->load('patient:id,full_name,municipality,document_type,document_number');

        $this->auditor->recordHistoryAccess($clinicalHistory, $request);

        // Las notas quedan en la teleconsulta, no en esta tabla: se anexan aquí
        // en lectura para que la historia sea el único lugar donde revisarlas.
        $teleconsultationNotes = Teleconsultation::query()
            ->whereNotNull('notes')
            ->whereHas('appointment', fn ($query) => $query->where('patient_id', $clinicalHistory->patient_id))
            ->with(['appointment' => fn ($query) => $query->select('id', 'doctor_id', 'scheduled_at')->with('doctor:id,name')])
            ->latest('updated_at')
            ->get(['id', 'appointment_id', 'notes', 'updated_at'])
            ->map(fn (Teleconsultation $teleconsultation) => [
                'id' => $teleconsultation->id,
                'notes' => $teleconsultation->notes,
                'scheduledAt' => $teleconsultation->appointment->scheduled_at,
                'doctorName' => $teleconsultation->appointment->doctor?->name,
            ]);

        return Inertia::render('historias-clinicas/show', [
            'clinicalHistory' => $clinicalHistory,
            'teleconsultationNotes' => $teleconsultationNotes,
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

        $clinicalHistory->load('patient');

        $this->auditor->recordHistoryAccess($clinicalHistory, $request);

        return view('clinical-history-print', [
            'history' => $clinicalHistory,
            'patient' => $clinicalHistory->patient,
            'printedBy' => $request->user(),
        ]);
    }
}
