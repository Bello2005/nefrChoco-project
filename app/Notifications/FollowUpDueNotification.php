<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * "Te toca medirte": recordatorio al paciente cuando un control remoto vence.
 *
 * Solo por base de datos, como el resto de avisos: sin correo ni SMS. No dice
 * valores ni niveles de riesgo, solo qué medición falta.
 */
class FollowUpDueNotification extends Notification
{
    use Queueable;

    /** @param  array<int, string>  $labels */
    public function __construct(
        private readonly array $labels,
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
            'type' => 'seguimiento_vencido',
            'title' => 'Te toca medirte',
            'message' => 'Registra tu '.mb_strtolower(implode(', ', $this->labels)).' cuando puedas.',
            'url' => route('paciente.signos-vitales.index', absolute: false),
        ];
    }
}
