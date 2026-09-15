<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Teleconsultation;
use Illuminate\Support\Str;

class TeleconsultationService
{
    public function createForAppointment(Appointment $appointment): Teleconsultation
    {
        return $appointment->teleconsultation()->create([
            'room_name' => $this->generateRoomName(),
            'status' => Teleconsultation::STATUS_PENDING,
        ]);
    }

    public function findOrCreateForAppointment(Appointment $appointment): Teleconsultation
    {
        return $appointment->teleconsultation ?? $this->createForAppointment($appointment);
    }

    /**
     * Cierra la teleconsulta con sus notas clínicas.
     *
     * Cerrar la sala también completa la cita: son el mismo hecho asistencial y
     * dejarlos desalineados haría que la agenda reportara atenciones pendientes
     * que en realidad ya ocurrieron.
     */
    public function complete(Teleconsultation $teleconsultation, string $notes): Teleconsultation
    {
        $teleconsultation->update([
            'notes' => $notes,
            'status' => Teleconsultation::STATUS_FINISHED,
        ]);

        $teleconsultation->appointment->update(['status' => Appointment::STATUS_COMPLETED]);

        return $teleconsultation;
    }

    private function generateRoomName(): string
    {
        return 'nefrochoco-'.Str::uuid()->toString();
    }
}
