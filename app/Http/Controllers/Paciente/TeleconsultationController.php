<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\TeleconsultationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        Gate::authorize('joinTeleconsultation', $appointment);

        if ($reason = $this->teleconsultationService->joinBlockedReason($appointment)) {
            return to_route('paciente.mis-citas.index')->with('error', $reason);
        }

        // La atención por videollamada se autoriza aparte del tratamiento de
        // datos (Resolución 2654 de 2019), y se pide aquí, con la cita a la
        // vista, en vez de en un muro general al iniciar sesión.
        if (! Auth::user()->patient?->hasCurrentTeleconsultationConsent()) {
            return to_route('paciente.mis-citas.teleconsulta.consentimiento', $appointment);
        }

        // La misma fila que abre el médico, así ambos caen en la misma sala.
        $teleconsultation = $this->teleconsultationService->findOrCreateForAppointment($appointment);

        return Inertia::render('paciente/teleconsulta/show', [
            'appointment' => $appointment->load('doctor:id,name'),
            // Solo el nombre de la sala: las notas de la teleconsulta son del
            // profesional y no se exponen en la pantalla del paciente.
            'roomName' => $teleconsultation->room_name,
            'jitsiDomain' => config('services.jitsi.domain'),
        ]);
    }
}
