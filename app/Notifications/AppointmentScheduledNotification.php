<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Avisa al paciente que le agendaron una cita, con su fecha y modalidad. */
class AppointmentScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Appointment $appointment,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $isRemote = $this->appointment->type === Appointment::TYPE_TELECONSULTATION;

        return [
            'type' => 'cita_agendada',
            'title' => $isRemote ? 'Nueva teleconsulta agendada' : 'Nueva cita agendada',
            'message' => sprintf(
                'Tienes una cita %s el %s.',
                $isRemote ? 'por videollamada' : 'presencial',
                $this->appointment->scheduled_at->locale('es')->isoFormat('D [de] MMMM [a las] h:mm a'),
            ),
            'appointment_id' => $this->appointment->id,
            'url' => route('paciente.mis-citas.index', absolute: false),
        ];
    }
}
