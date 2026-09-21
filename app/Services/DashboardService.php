<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
        private readonly ClinicalDecisionSupport $clinicalDecisionSupport,
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    /** @return array<string, mixed> */
    public function forDoctor(User $doctor): array
    {
        $today = CarbonImmutable::today();
        $doctorAppointments = Appointment::where('doctor_id', $doctor->id);

        return [
            'stats' => [
                'patients' => Patient::count(),
                'appointmentsToday' => (clone $doctorAppointments)
                    ->whereBetween('scheduled_at', [$today->startOfDay(), $today->endOfDay()])
                    ->count(),
                'appointmentsWeek' => (clone $doctorAppointments)
                    ->whereBetween('scheduled_at', [$today->startOfWeek(), $today->endOfWeek()])
                    ->count(),
                'pendingTeleconsultations' => (clone $doctorAppointments)
                    ->where('type', Appointment::TYPE_TELECONSULTATION)
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->count(),
            ],
            'upcomingAppointments' => (clone $doctorAppointments)
                ->with('patient:id,full_name,municipality')
                ->where('scheduled_at', '>=', now())
                ->where('status', Appointment::STATUS_SCHEDULED)
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(['id', 'patient_id', 'scheduled_at', 'status', 'type']),
            // La agenda del día vista desde la sala: incluye las ya cerradas y las
            // canceladas, porque el profesional necesita ver qué pasó hoy, no solo
            // lo que le queda por atender.
            'todayTeleconsultations' => (clone $doctorAppointments)
                ->with('patient:id,full_name,municipality')
                ->where('type', Appointment::TYPE_TELECONSULTATION)
                ->whereBetween('scheduled_at', [$today->startOfDay(), $today->endOfDay()])
                ->orderBy('scheduled_at')
                ->get(['id', 'patient_id', 'scheduled_at', 'status', 'type'])
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'scheduledAt' => $appointment->scheduled_at->toIso8601String(),
                    'status' => $appointment->status,
                    'patientName' => $appointment->patient?->full_name,
                    'municipality' => $appointment->patient?->municipality,
                    'blockedReason' => $this->teleconsultationService->doctorJoinBlockedReason($appointment),
                ]),
            // Apoyo a decisiones: a quién conviene revisar primero y por qué.
            'priorityPatients' => $this->clinicalDecisionSupport->priorityPatients($doctor),
            'ecntDistribution' => $this->ecntDistribution(),
            'appointmentsTrend' => $this->appointmentsTrend($doctor),
            'alerts' => $this->vitalSignService->outOfRangeAlerts()->map(fn ($sign) => [
                'id' => $sign->id,
                'patient' => $sign->patient?->full_name,
                'patientId' => $sign->patient_id,
                'label' => $sign->typeEnum()?->shortLabel() ?? $sign->type,
                'value' => (float) $sign->value,
                'unit' => $sign->unit,
                'status' => $sign->status(),
                'recordedAt' => $sign->recorded_at->toIso8601String(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function forAdmin(): array
    {
        $monthStart = CarbonImmutable::today()->startOfMonth();

        return [
            'stats' => [
                'users' => User::count(),
                'patients' => Patient::count(),
                'appointmentsMonth' => Appointment::where('scheduled_at', '>=', $monthStart)->count(),
                'clinicalForms' => ClinicalForm::count(),
            ],
            'usersByRole' => User::with('roles')
                ->get()
                ->groupBy(fn (User $user) => $user->roles->first()?->name ?? 'sin_rol')
                ->map->count(),
            'patientsByMunicipality' => Patient::query()
                ->selectRaw('municipality, count(*) as total')
                ->groupBy('municipality')
                ->orderByDesc('total')
                ->limit(6)
                ->get()
                ->map(fn ($row) => ['label' => $row->municipality, 'value' => (int) $row->total]),
            'recentActivity' => Activity::with('causer')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'subject' => class_basename($activity->subject_type ?? ''),
                    'causer' => $activity->causer?->name ?? 'Sistema',
                    'createdAt' => $activity->created_at->toIso8601String(),
                ]),
        ];
    }

    /**
     * Distribución de diagnósticos ECNT.
     *
     * El agrupamiento ocurre en PHP y no en SQL porque `ecnt_diagnosis` está
     * cifrado en reposo: un GROUP BY sobre la columna agruparía textos cifrados
     * y devolvería un grupo por registro. No mover esta lógica a la consulta.
     *
     * @return Collection<int, array{label: string, value: int}>
     */
    private function ecntDistribution(): Collection
    {
        return ClinicalHistory::query()
            ->whereNotNull('ecnt_diagnosis')
            ->get(['ecnt_diagnosis'])
            ->groupBy('ecnt_diagnosis')
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->map(fn (int $total, string $diagnosis) => ['label' => $diagnosis, 'value' => $total])
            ->values();
    }

    /**
     * Citas por día de la última semana. Se agrupa en PHP en lugar de SQL
     * para que la consulta funcione igual en PostgreSQL y en SQLite (tests).
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function appointmentsTrend(User $doctor): array
    {
        $start = CarbonImmutable::today()->subDays(6);

        $counts = Appointment::where('doctor_id', $doctor->id)
            ->where('scheduled_at', '>=', $start->startOfDay())
            ->where('scheduled_at', '<=', CarbonImmutable::today()->endOfDay())
            ->get(['scheduled_at'])
            ->groupBy(fn (Appointment $appointment) => $appointment->scheduled_at->toDateString())
            ->map->count();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $counts) {
                $day = $start->addDays($offset);

                return [
                    'label' => ucfirst($day->locale('es')->isoFormat('ddd')),
                    'value' => $counts[$day->toDateString()] ?? 0,
                ];
            })
            ->all();
    }
}
