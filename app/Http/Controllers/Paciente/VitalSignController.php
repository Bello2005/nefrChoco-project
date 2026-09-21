<?php

namespace App\Http\Controllers\Paciente;

use App\Enums\VitalSignType;
use App\Http\Controllers\Controller;
use App\Http\Requests\VitalSign\StoreVitalSignRequest;
use App\Services\VitalSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VitalSignController extends Controller
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $patient = $user->patient;

        return Inertia::render('paciente/signos-vitales/index', [
            'series' => $patient ? $this->vitalSignService->seriesFor($patient) : [],
            'types' => VitalSignType::options(),
            'hasProfile' => $patient !== null,
            'showGuide' => $user->vital_signs_guide_dismissed_at === null,
        ]);
    }

    /** El paciente ya leyó la guía: no se la volvemos a mostrar. */
    public function dismissGuide(): RedirectResponse
    {
        Auth::user()->forceFill(['vital_signs_guide_dismissed_at' => now()])->save();

        return back();
    }

    public function store(StoreVitalSignRequest $request): RedirectResponse|JsonResponse
    {
        $patient = Auth::user()->patient;

        if (! $patient) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Cuenta sin ficha de paciente vinculada.'], 422)
                : back()->with('error', 'Tu cuenta aún no está vinculada a una ficha de paciente.');
        }

        $this->vitalSignService->record($patient, Auth::user(), $request->validated());

        // La cola offline envía por fetch y no necesita la página de vuelta.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Medición sincronizada.']);
        }

        return back()->with('success', 'Medición registrada. Tu equipo médico podrá verla.');
    }
}
