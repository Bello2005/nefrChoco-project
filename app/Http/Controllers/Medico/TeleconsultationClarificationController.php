<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teleconsultation\StoreTeleconsultationClarificationRequest;
use App\Models\Appointment;
use App\Services\TeleconsultationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TeleconsultationClarificationController extends Controller
{
    public function __construct(
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    public function store(StoreTeleconsultationClarificationRequest $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('clarifyTeleconsultation', $appointment);

        try {
            $this->teleconsultationService->addClarification(
                $appointment->teleconsultation,
                $request->user(),
                $request->string('body')->toString(),
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Aclaración agregada.');
    }
}
