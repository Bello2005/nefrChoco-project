<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
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

    /**
     * Motivo por el que el paciente no puede entrar a la sala, o null si sí puede.
     *
     * Devuelve el texto y no un booleano porque en zonas con mala señal el
     * paciente necesita saber qué hacer: esperar, reintentar o llamar a la IPS.
     */
    public function joinBlockedReason(Appointment $appointment): ?string
    {
        if ($appointment->type !== Appointment::TYPE_TELECONSULTATION) {
            return 'Esta cita es presencial, no tiene sala de videollamada.';
        }

        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return match ($appointment->status) {
                Appointment::STATUS_COMPLETED => 'Esta teleconsulta ya fue cerrada por el profesional.',
                Appointment::STATUS_CANCELLED => 'Esta cita fue cancelada. Comunícate con la IPS para reagendar.',
                default => 'Esta cita ya no está activa. Comunícate con la IPS para reagendar.',
            };
        }

        $minutesBefore = (int) config('teleconsultation.join_window.minutes_before');
        $minutesAfter = (int) config('teleconsultation.join_window.minutes_after');

        // copy() porque Carbon es mutable y restarle minutos al atributo
        // corrompería la fecha de la cita en memoria.
        if (now()->lt($appointment->scheduled_at->copy()->subMinutes($minutesBefore))) {
            return "La sala se abre {$minutesBefore} minutos antes de la hora de tu cita.";
        }

        if (now()->gt($appointment->scheduled_at->copy()->addMinutes($minutesAfter))) {
            return 'La sala de esta teleconsulta ya se cerró. Comunícate con la IPS para reagendar.';
        }

        return null;
    }

    public function patientCanJoin(Appointment $appointment): bool
    {
        return $this->joinBlockedReason($appointment) === null;
    }

    /**
     * Teleconsulta del paciente que ya se puede abrir ahora mismo, si la hay.
     *
     * Una consulta que empezó hace diez minutos deja de ser "la próxima cita",
     * y es justo cuando el paciente más necesita el acceso a la sala.
     */
    public function joinableForPatient(Patient $patient): ?Appointment
    {
        $minutesBefore = (int) config('teleconsultation.join_window.minutes_before');
        $minutesAfter = (int) config('teleconsultation.join_window.minutes_after');

        return $patient->appointments()
            ->with('doctor:id,name')
            ->where('type', Appointment::TYPE_TELECONSULTATION)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereBetween('scheduled_at', [now()->subMinutes($minutesAfter), now()->addMinutes($minutesBefore)])
            ->orderBy('scheduled_at')
            ->first(['id', 'doctor_id', 'scheduled_at', 'status', 'type']);
    }

    private function generateRoomName(): string
    {
        return 'nefrochoco-'.Str::uuid()->toString();
    }
}
