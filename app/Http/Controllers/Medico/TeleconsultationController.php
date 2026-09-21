<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teleconsultation\UpdateTeleconsultationRequest;
use App\Models\Appointment;
use App\Services\TeleconsultationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeleconsultationController extends Controller
{
    public function __construct(
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    public function show(Appointment $appointment): Response|RedirectResponse
    {
        Gate::authorize('manageTeleconsultation', $appointment);

        if ($appointment->type !== Appointment::TYPE_TELECONSULTATION) {
            return to_route('medico.citas.index')->with('error', 'Esta cita no es una teleconsulta.');
        }

        if ($reason = $this->teleconsultationService->doctorJoinBlockedReason($appointment)) {
            return to_route('medico.citas.index')->with('error', $reason);
        }

        $teleconsultation = $this->teleconsultationService->findOrCreateForAppointment($appointment);

        return Inertia::render('medico/teleconsulta/show', [
            'appointment' => $appointment->load('patient:id,full_name'),
            'teleconsultation' => $teleconsultation,
            'jitsiDomain' => config('services.jitsi.domain'),
        ]);
    }

    public function complete(UpdateTeleconsultationRequest $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('manageTeleconsultation', $appointment);

        $teleconsultation = $this->teleconsultationService->findOrCreateForAppointment($appointment);

        $this->teleconsultationService->complete($teleconsultation, $request->string('notes')->toString());

        return to_route('medico.citas.index')->with('success', 'Teleconsulta cerrada y cita marcada como completada.');
    }
}
