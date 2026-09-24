<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mis consentimientos": dónde ve el paciente lo que autorizó y cómo retirarlo.
 *
 * Solo trabaja sobre la ficha de quien inició sesión: la ruta no recibe
 * ningún identificador, así que nadie puede retirar el consentimiento de otro.
 */
class ConsentsController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    public function show(Request $request): Response
    {
        $patient = $request->user()->patient;

        return Inertia::render('paciente/consentimientos', [
            'dataConsent' => $patient ? [
                'acceptedAt' => $patient->consent_accepted_at,
                'version' => $patient->consent_version,
            ] : null,
            'teleconsultationConsent' => $patient ? [
                'acceptedAt' => $patient->teleconsultation_consent_accepted_at,
                'revokedAt' => $patient->teleconsultation_consent_revoked_at,
                'isCurrent' => $patient->hasCurrentTeleconsultationConsent(),
            ] : null,
            'contactEmail' => config('privacy.contact_email'),
        ]);
    }

    public function revokeTeleconsultation(Request $request): RedirectResponse
    {
        $patient = $request->user()->patient;

        if (! $patient) {
            return back()->with('error', 'Tu cuenta aún no está vinculada a una ficha de paciente.');
        }

        $this->consentService->revokeTeleconsultation($patient, $request->user());

        return to_route('paciente.mis-consentimientos.show')
            ->with('success', 'Retiraste tu autorización. Si más adelante quieres una teleconsulta, te la volvemos a pedir.');
    }
}
