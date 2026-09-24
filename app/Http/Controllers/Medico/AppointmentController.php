<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\AttentionRecordService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(): Response
    {
        // doctor_id va en las columnas porque la política lo compara, y la
        // teleconsulta se carga de una vez porque isAttended() la consulta:
        // sin eso sería una consulta por cada cita de la lista.
        $appointments = Appointment::query()
            ->with(['patient:id,full_name', 'teleconsultation:id,appointment_id,status'])
            ->where('doctor_id', Auth::id())
            ->orderBy('scheduled_at')
            ->get(['id', 'patient_id', 'doctor_id', 'scheduled_at', 'status', 'type', 'connection_check_level', 'connection_check_at'])
            ->map(fn (Appointment $appointment) => [
                ...$appointment->only(['id', 'scheduled_at', 'status', 'type', 'patient']),
                'can_edit' => Gate::allows('update', $appointment),
                // Último "Probar mi conexión" del paciente: solo el nivel y la fecha.
                'connection_check' => $appointment->connection_check_level ? [
                    'level' => $appointment->connection_check_level,
                    'at' => $appointment->connection_check_at,
                ] : null,
            ]);

        return Inertia::render('medico/citas/index', [
            'appointments' => $appointments,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('medico/citas/create', [
            'patients' => Patient::orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $this->appointmentService->create([
            ...$request->validated(),
            'doctor_id' => Auth::id(),
        ]);

        return to_route('medico.citas.index')->with('success', 'Cita agendada correctamente.');
    }

    public function edit(Appointment $appointment): Response
    {
        Gate::authorize('update', $appointment);

        return Inertia::render('medico/citas/edit', [
            'appointment' => $appointment,
            'attentionCatalogs' => app(AttentionRecordService::class)->availability(),
            'patients' => Patient::orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);

        $this->appointmentService->update($appointment, $request->validated(), $request->user());

        return to_route('medico.citas.index')->with('success', 'Cita actualizada correctamente.');
    }
}
