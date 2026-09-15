<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Abrir la sala de teleconsulta desde la zona del paciente.
     *
     * La sala es el punto de encuentro de una atención clínica, no un recurso
     * con enlace público: un paciente solo entra a la de una cita suya, aunque
     * manipule el identificador en la URL.
     */
    public function joinTeleconsultation(User $user, Appointment $appointment): bool
    {
        return $user->patient !== null
            && $appointment->patient_id === $user->patient->id;
    }
}
