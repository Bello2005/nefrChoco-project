<?php

namespace App\Services;

use App\Models\ClinicalHistory;
use App\Models\User;

/**
 * Registro de accesos a datos clínicos sensibles.
 *
 * La Ley 1581 de 2012 exige poder demostrar quién consultó una historia clínica
 * y cuándo, no solo quién la modificó: por eso se registra también la lectura,
 * que el log de cambios por sí solo nunca capturaría.
 */
class ClinicalAccessAuditor
{
    public const LOG_NAME = 'acceso_clinico';

    public function recordHistoryAccess(ClinicalHistory $clinicalHistory, User $user, ?string $ipAddress = null): void
    {
        activity(self::LOG_NAME)
            ->causedBy($user)
            ->performedOn($clinicalHistory)
            ->withProperties([
                'paciente' => $clinicalHistory->patient->full_name,
                'ip' => $ipAddress,
            ])
            ->event('consultado')
            ->log('Consultó una historia clínica');
    }
}
