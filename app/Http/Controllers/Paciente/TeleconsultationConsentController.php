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
 * (Resolución 1644 de 2026, art. 7).
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

        // Aceptar de nuevo deja sin efecto un retiro anterior; la fecha del
        // retiro vive en la auditoría (teleconsulta_revocada).
        $patient->forceFill([
            'teleconsultation_consent_accepted_at' => now(),
            'teleconsultation_consent_version' => config('privacy.teleconsultation_consent_version'),
            'teleconsultation_consent_revoked_at' => null,
        ])->save();

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
