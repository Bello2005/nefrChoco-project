<?php

namespace App\Http\Controllers;

use App\Models\ClinicalHistory;
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

        $this->auditor->recordHistoryAccess($clinicalHistory, $request->user(), $request->ip());

        return Inertia::render('historias-clinicas/show', [
            'clinicalHistory' => $clinicalHistory,
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

        $this->auditor->recordHistoryAccess($clinicalHistory, $request->user(), $request->ip());

        return view('clinical-history-print', [
            'history' => $clinicalHistory,
            'patient' => $clinicalHistory->patient,
            'printedBy' => $request->user(),
        ]);
    }
}
