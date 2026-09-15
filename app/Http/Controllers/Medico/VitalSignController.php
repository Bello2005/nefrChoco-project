<?php

namespace App\Http\Controllers\Medico;

use App\Enums\VitalSignType;
use App\Http\Controllers\Controller;
use App\Http\Requests\VitalSign\StoreVitalSignRequest;
use App\Models\Patient;
use App\Services\VitalSignService;
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
        $patients = Patient::query()
            ->withCount('vitalSigns')
            ->with(['vitalSigns' => fn ($query) => $query->latest('recorded_at')->limit(1)])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => $patient->full_name,
                'municipality' => $patient->municipality,
                'readings' => $patient->vital_signs_count,
                'lastReading' => $patient->vitalSigns->first() ? [
                    'label' => $patient->vitalSigns->first()->typeEnum()?->shortLabel(),
                    'value' => (float) $patient->vitalSigns->first()->value,
                    'unit' => $patient->vitalSigns->first()->unit,
                    'status' => $patient->vitalSigns->first()->status(),
                    'recordedAt' => $patient->vitalSigns->first()->recorded_at->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('medico/telemonitoreo/index', [
            'patients' => $patients,
            'alerts' => $this->vitalSignService->outOfRangeAlerts(6)->map(fn ($sign) => [
                'id' => $sign->id,
                'patient' => $sign->patient?->full_name,
                'patientId' => $sign->patient_id,
                'label' => $sign->typeEnum()?->shortLabel() ?? $sign->type,
                'value' => (float) $sign->value,
                'unit' => $sign->unit,
                'status' => $sign->status(),
                'recordedAt' => $sign->recorded_at->toIso8601String(),
            ]),
        ]);
    }

    public function show(Patient $patient): Response
    {
        return Inertia::render('medico/telemonitoreo/show', [
            'patient' => $patient->only(['id', 'full_name', 'municipality', 'document_number']),
            'series' => $this->vitalSignService->seriesFor($patient),
            'types' => VitalSignType::options(),
        ]);
    }

    public function store(StoreVitalSignRequest $request, Patient $patient)
    {
        $this->vitalSignService->record($patient, Auth::user(), $request->validated());

        // La cola offline envía por fetch y no necesita la página de vuelta.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Medición sincronizada.']);
        }

        return back()->with('success', 'Medición registrada correctamente.');
    }
}
