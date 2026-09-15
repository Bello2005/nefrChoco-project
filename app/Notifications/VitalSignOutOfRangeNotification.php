<?php

namespace App\Notifications;

use App\Models\VitalSign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avisa al médico tratante cuando una medición llega fuera del rango de referencia.
 *
 * Solo por base de datos: en zonas con conectividad intermitente el correo puede
 * tardar horas y el push está fuera de alcance, así que el aviso vive donde el
 * profesional sí va a entrar.
 */
class VitalSignOutOfRangeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly VitalSign $vitalSign,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'signo_vital_fuera_de_rango',
            'title' => 'Medición fuera de rango',
            'message' => sprintf(
                '%s registró %s de %s %s.',
                $this->vitalSign->patient->full_name,
                mb_strtolower($this->vitalSign->typeEnum()?->shortLabel() ?? $this->vitalSign->type),
                // El casting decimal deja "87.00": se recortan los ceros sobrantes
                // para que el aviso se lea como lo diría un profesional.
                rtrim(rtrim((string) $this->vitalSign->value, '0'), '.'),
                $this->vitalSign->unit,
            ),
            'status' => $this->vitalSign->status(),
            'patient_id' => $this->vitalSign->patient_id,
            'url' => route('medico.telemonitoreo.show', $this->vitalSign->patient_id, false),
        ];
    }
}
