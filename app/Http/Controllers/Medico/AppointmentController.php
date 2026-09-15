<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
    ) {}

    public function index(): Response
    {
        $appointments = Appointment::query()
            ->with('patient:id,full_name')
            ->where('doctor_id', Auth::id())
            ->orderBy('scheduled_at')
            ->get(['id', 'patient_id', 'scheduled_at', 'status', 'type']);

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
        return Inertia::render('medico/citas/edit', [
            'appointment' => $appointment,
            'patients' => Patient::orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $this->appointmentService->update($appointment, $request->validated());

        return to_route('medico.citas.index')->with('success', 'Cita actualizada correctamente.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->appointmentService->delete($appointment);

        return to_route('medico.citas.index')->with('success', 'Cita eliminada correctamente.');
    }
}
