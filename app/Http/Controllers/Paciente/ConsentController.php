<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsentController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $patient = $request->user()->patient;

        if ($patient?->hasCurrentConsent()) {
            return to_route('paciente.dashboard');
        }

        return Inertia::render('paciente/consentimiento', [
            'version' => config('privacy.consent_version'),
            'contactEmail' => config('privacy.contact_email'),
            'hasProfile' => $patient !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['accepted' => ['accepted']],
            ['accepted.accepted' => 'Debes autorizar el tratamiento de tus datos para continuar.'],
        );

        $patient = $request->user()->patient;

        if (! $patient) {
            return back()->with('error', 'Tu cuenta aún no está vinculada a una ficha de paciente.');
        }

        $patient->update([
            'consent_accepted_at' => now(),
            'consent_version' => config('privacy.consent_version'),
        ]);

        activity('consentimiento')
            ->causedBy($request->user())
            ->performedOn($patient)
            ->withProperties(['version' => config('privacy.consent_version'), 'ip' => $request->ip()])
            ->event('autorizado')
            ->log('Autorizó el tratamiento de sus datos personales');

        return to_route('paciente.dashboard')->with('success', 'Gracias. Registramos tu autorización.');
    }
}
