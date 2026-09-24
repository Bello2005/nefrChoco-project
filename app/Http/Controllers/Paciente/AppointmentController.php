<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\TeleconsultationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    public function index(): Response
    {
        $patient = Auth::user()->patient;

        $appointments = $patient
            ? Appointment::query()
                ->with('doctor:id,name')
                ->where('patient_id', $patient->id)
                ->orderBy('scheduled_at')
                ->get(['id', 'doctor_id', 'scheduled_at', 'status', 'type', 'connection_check_level', 'connection_check_at'])
                // can_join se calcula en el servidor para que el botón y la
                // autorización real usen exactamente la misma regla.
                ->map(fn (Appointment $appointment) => [
                    'id' => $appointment->id,
                    'scheduled_at' => $appointment->scheduled_at,
                    'status' => $appointment->status,
                    'type' => $appointment->type,
                    'doctor' => $appointment->doctor,
                    'can_join' => $this->teleconsultationService->patientCanJoin($appointment),
                    'connection_check' => $appointment->connection_check_level ? [
                        'level' => $appointment->connection_check_level,
                        'at' => $appointment->connection_check_at,
                    ] : null,
                ])
            : collect();

        return Inertia::render('paciente/mis-citas/index', [
            'appointments' => $appointments,
        ]);
    }
}
