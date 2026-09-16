<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consentimiento informado para ser atendido por videollamada
 * (Resolución 2654 de 2019).
 *
 * Se pide al entrar a la sala y no en un muro general al iniciar sesión: es
 * una autorización sobre la modalidad de atención, así que tiene sentido justo
 * cuando la persona va a usarla, y con la cita concreta a la vista.
 */
class TeleconsultationConsentController extends Controller
{
    public function show(Request $request, Appointment $appointment): Response|RedirectResponse
    {
        Gate::authorize('joinTeleconsultation', $appointment);

        $patient = $request->user()->patient;

        if ($patient?->hasCurrentTeleconsultationConsent()) {
            return to_route('paciente.mis-citas.teleconsulta', $appointment);
        }

        return Inertia::render('paciente/teleconsulta/consentimiento', [
            'appointment' => [
                'id' => $appointment->id,
                'scheduled_at' => $appointment->scheduled_at,
                'doctor' => $appointment->doctor()->first(['id', 'name']),
            ],
            'version' => config('privacy.teleconsultation_consent_version'),
            'contactEmail' => config('privacy.contact_email'),
        ]);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('joinTeleconsultation', $appointment);

        $request->validate(
            ['accepted' => ['accepted']],
            ['accepted.accepted' => 'Debes autorizar la atención por videollamada para entrar a la sala.'],
        );

        $patient = $request->user()->patient;

        if (! $patient) {
            return back()->with('error', 'Tu cuenta aún no está vinculada a una ficha de paciente.');
        }

        $patient->update([
            'teleconsultation_consent_accepted_at' => now(),
            'teleconsultation_consent_version' => config('privacy.teleconsultation_consent_version'),
        ]);

        activity('consentimiento')
            ->causedBy($request->user())
            ->performedOn($patient)
            ->withProperties([
                'version' => config('privacy.teleconsultation_consent_version'),
                'cita' => $appointment->id,
                'ip' => $request->ip(),
            ])
            ->event('teleconsulta_autorizada')
            ->log('Autorizó ser atendido por videollamada');

        return to_route('paciente.mis-citas.teleconsulta', $appointment);
    }
}
