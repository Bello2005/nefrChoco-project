<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Teleconsultation;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Gestionar la cita: reprogramarla, cambiar su estado o cancelarla.
     *
     * El padrón de pacientes es institucional, pero la agenda no: una cita es
     * el compromiso de un profesional concreto con una persona, y dejar que
     * otro médico la mueva o la cancele rompe tanto la agenda ajena como la
     * trazabilidad de quién decidió qué.
     *
     * Una cita atendida ya es parte del registro: cambiarle el paciente
     * mudaría sus notas a la historia de otra persona, y cambiarle la fecha o
     * el estado reescribiría cuándo y cómo se atendió. Las canceladas y las de
     * inasistencia sí se editan, para corregir errores de agenda.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->isTreatingDoctor($user, $appointment)
            && ! $appointment->isAttended();
    }

    /**
     * Abrir la sala de teleconsulta y cerrarla con sus notas clínicas.
     *
     * Es la acción más sensible del sistema: las notas quedan firmadas en la
     * historia del paciente, así que solo puede hacerlas el profesional que
     * efectivamente atiende esa cita.
     */
    public function manageTeleconsultation(User $user, Appointment $appointment): bool
    {
        return $this->isTreatingDoctor($user, $appointment);
    }

    /**
     * Agregar una aclaración a la nota de una teleconsulta ya cerrada.
     *
     * Solo el médico de la cita, igual que la nota: solo él firma lo que
     * dijo en esa atención. Y solo con la sala cerrada, porque mientras está
     * abierta la nota todavía se escribe al cerrarla.
     */
    public function clarifyTeleconsultation(User $user, Appointment $appointment): bool
    {
        return $this->isTreatingDoctor($user, $appointment)
            && $appointment->teleconsultation?->status === Teleconsultation::STATUS_FINISHED;
    }

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

    private function isTreatingDoctor(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('medico')
            && $appointment->doctor_id === $user->id;
    }
}
