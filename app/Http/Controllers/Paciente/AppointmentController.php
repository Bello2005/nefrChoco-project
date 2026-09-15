<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(): Response
    {
        $patient = Auth::user()->patient;

        $appointments = $patient
            ? Appointment::query()
                ->with('doctor:id,name')
                ->where('patient_id', $patient->id)
                ->orderBy('scheduled_at')
                ->get(['id', 'doctor_id', 'scheduled_at', 'status', 'type'])
            : collect();

        return Inertia::render('paciente/mis-citas/index', [
            'appointments' => $appointments,
        ]);
    }
}
