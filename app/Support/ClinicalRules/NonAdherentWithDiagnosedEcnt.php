<?php

namespace App\Support\ClinicalRules;

use App\Support\ClinicalFormCatalog;

/**
 * Falta de adherencia en alguien que ya tiene una ECNT diagnosticada.
 *
 * En una enfermedad crónica ya diagnosticada, dejar de tomar el tratamiento no
 * es un descuido menor: es la vía más frecuente a la descompensación. Y en el
 * Chocó rural la causa suele ser una barrera concreta (distancia, costo,
 * desabastecimiento) que el test de Morisky-Green ya recoge por escrito.
 */
final class NonAdherentWithDiagnosedEcnt implements ClinicalRule
{
    public function key(): string
    {
        return 'no_adherencia_con_ecnt_diagnosticada';
    }

    public function evaluate(PatientSignals $signals): ?Recommendation
    {
        $adherence = $signals->adherence;

        $concerning = in_array(
            $adherence?->risk_level,
            (array) config('clinical_support.adherence.concerning_levels'),
            true,
        );

        if (! $concerning || ! $signals->hasDiagnosedEcnt()) {
            return null;
        }

        $barreras = trim((string) ($adherence->answers['barreras'] ?? ''));

        return new Recommendation(
            ruleKey: $this->key(),
            priority: Priority::Medium,
            title: 'No adherencia sobre una ECNT ya diagnosticada',
            reason: sprintf(
                'El test de Morisky-Green dio %s (puntaje %d) el %s, en un paciente con diagnóstico de %s.%s',
                str_replace('_', ' ', (string) $adherence->risk_level),
                (int) $adherence->score,
                $adherence->created_at->locale('es')->isoFormat('D [de] MMMM'),
                $signals->ecntDiagnosis,
                $barreras !== '' ? ' Barreras registradas: '.$barreras : '',
            ),
            action: ClinicalFormCatalog::adviceFor('adherencia_tratamiento', $adherence->risk_level)
                ?? 'Reforzar educación y acordar un plan de seguimiento con el paciente.',
        );
    }
}
