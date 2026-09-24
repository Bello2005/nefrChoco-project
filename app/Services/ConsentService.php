<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;

class ConsentService
{
    /**
     * Retira la autorización para ser atendido por videollamada (Res. 1644
     * de 2026, art. 7: el consentimiento es revocable).
     *
     * No borra la aceptación: la fecha en que la persona autorizó respalda
     * las teleconsultas que ya se hicieron. Solo marca el retiro, y a partir
     * de ahí la sala exige aceptar de nuevo. La auditoría registra quién y
     * cuándo, sin valores.
     */
    public function revokeTeleconsultation(Patient $patient, User $user): void
    {
        if ($patient->teleconsultation_consent_revoked_at !== null) {
            return;
        }

        $patient->forceFill(['teleconsultation_consent_revoked_at' => now()])->save();

        activity('consentimiento')
            ->causedBy($user)
            ->performedOn($patient)
            ->event('teleconsulta_revocada')
            ->log('Retiró la autorización de teleconsulta');
    }
}
