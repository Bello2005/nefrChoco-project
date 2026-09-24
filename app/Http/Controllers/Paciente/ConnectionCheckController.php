<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Probar mi conexión" antes de la teleconsulta (Res. 1644 de 2026, art. 24.8).
 *
 * La prueba corre entera en el navegador contra un archivo propio: nada sale
 * hacia terceros. El servidor solo guarda el nivel resultante y la fecha, para
 * que el médico de la cita lo vea antes de abrir la sala.
 */
class ConnectionCheckController extends Controller
{
    public function show(Appointment $appointment): Response|RedirectResponse
    {
        Gate::authorize('joinTeleconsultation', $appointment);

        if ($appointment->type !== Appointment::TYPE_TELECONSULTATION) {
            return to_route('paciente.mis-citas.index')->with('error', 'Esta cita es presencial, no necesita videollamada.');
        }

        return Inertia::render('paciente/teleconsulta/probar-conexion', [
            'appointment' => [
                'id' => $appointment->id,
                'scheduled_at' => $appointment->scheduled_at,
            ],
            'thresholds' => config('teleconsultation.connection_check'),
        ]);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('joinTeleconsultation', $appointment);

        $validated = $request->validate([
            'level' => ['required', Rule::in(Appointment::CONNECTION_LEVELS)],
        ]);

        // Sin pasar por update() masivo: son dos columnas técnicas que solo
        // se escriben desde aquí.
        $appointment->forceFill([
            'connection_check_level' => $validated['level'],
            'connection_check_at' => now(),
        ])->save();

        return back();
    }
}
