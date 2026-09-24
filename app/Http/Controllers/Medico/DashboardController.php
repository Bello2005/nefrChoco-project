<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\FollowUpScheduleService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly FollowUpScheduleService $followUpSchedule,
    ) {}

    /** @return list<string> */
    private function practitionerMissing(): array
    {
        $profile = Auth::user()->practitionerProfile;
        $missing = $profile?->missingFields() ?? ['tus datos profesionales'];

        if ($profile?->rethus_verified_at === null) {
            $missing[] = 'la verificación en RETHUS';
        }

        return $missing;
    }

    public function index(): Response
    {
        return Inertia::render('medico/dashboard', [
            ...$this->dashboardService->forDoctor(Auth::user()),
            'doctorName' => Auth::user()->name,
            // Aviso sin bloquear la atención: el RDA necesita el perfil profesional.
            'practitionerMissing' => $this->practitionerMissing(),
            // El padrón es institucional: cualquier médico ve los controles vencidos.
            'followUp' => [
                'isActive' => $this->followUpSchedule->isActive(),
                'overduePatients' => $this->followUpSchedule->overduePatients()
                    ->take(10)
                    ->map(fn (array $row) => [
                        'id' => $row['patient']->id,
                        'name' => $row['patient']->full_name,
                        'overdue' => $row['overdue']->pluck('label'),
                    ]),
            ],
        ]);
    }
}
