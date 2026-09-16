<?php

namespace App\Notifications;

use App\Models\VitalSign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Avisa al médico tratante cuando una medición llega fuera del rango de referencia.
 *
 * Recibe una o varias lecturas del mismo envío: la presión arterial se registra
 * como sistólica y diastólica a la vez, y si ambas salen de rango corresponde un
 * solo aviso que las mencione, no dos avisos por la misma toma.
 *
 * Solo por base de datos: en zonas con conectividad intermitente el correo puede
 * tardar horas y el push está fuera de alcance, así que el aviso vive donde el
 * profesional sí va a entrar.
 */
class VitalSignOutOfRangeNotification extends Notification
{
    use Queueable;

    /** @var Collection<int, VitalSign> */
    private readonly Collection $vitalSigns;

    /** @param  array<int, VitalSign>|Collection<int, VitalSign>  $vitalSigns */
    public function __construct(array|Collection $vitalSigns)
    {
        $this->vitalSigns = collect($vitalSigns)->values();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $primera = $this->vitalSigns->first();

        $detalle = $this->vitalSigns
            ->map(fn (VitalSign $sign) => sprintf(
                '%s de %s %s',
                mb_strtolower($sign->typeEnum()?->shortLabel() ?? $sign->type),
                // El casting decimal deja "87.00": se recortan los ceros
                // sobrantes para que el aviso se lea como lo diría alguien.
                rtrim(rtrim((string) $sign->value, '0'), '.'),
                $sign->unit,
            ))
            ->join(' y ');

        return [
            'type' => 'signo_vital_fuera_de_rango',
            'title' => $this->vitalSigns->count() > 1 ? 'Mediciones fuera de rango' : 'Medición fuera de rango',
            'message' => sprintf('%s registró %s.', $primera->patient->full_name, $detalle),
            'status' => $primera->status(),
            'patient_id' => $primera->patient_id,
            'url' => route('medico.telemonitoreo.show', $primera->patient_id, false),
        ];
    }
}
