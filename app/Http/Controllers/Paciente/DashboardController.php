<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\EducationalContent;
use App\Services\TeleconsultationService;
use App\Services\VitalSignService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $patient = $user->patient;

        return Inertia::render('paciente/dashboard', [
            'patientName' => $patient?->full_name ?? $user->name,
            'hasProfile' => $patient !== null,
            'activeTeleconsultation' => $patient ? $this->teleconsultationService->joinableForPatient($patient) : null,
            'nextAppointment' => $patient
                ?->appointments()
                ->with('doctor:id,name')
                ->where('scheduled_at', '>=', now())
                ->where('status', 'programada')
                ->orderBy('scheduled_at')
                ->first(['id', 'doctor_id', 'scheduled_at', 'status', 'type']),
            'upcomingCount' => $patient
                ?->appointments()
                ->where('scheduled_at', '>=', now())
                ->where('status', 'programada')
                ->count() ?? 0,
            'series' => $patient ? $this->vitalSignService->seriesFor($patient, 8) : [],
            'clinicalHistoryId' => $patient?->latestClinicalHistory?->id,
            'suggestedContents' => EducationalContent::latest()
                ->limit(3)
                ->get(['id', 'title', 'description', 'type', 'body', 'url_or_path', 'ecnt_category'])
                ->map(fn (EducationalContent $content) => [
                    'id' => $content->id,
                    'title' => $content->title,
                    'description' => $content->description,
                    'type' => $content->type,
                    'url_or_path' => $content->url_or_path,
                    'hasOwnBody' => $content->hasOwnBody(),
                ]),
        ]);
    }
}
