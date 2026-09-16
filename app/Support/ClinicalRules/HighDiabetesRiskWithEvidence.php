<?php

namespace App\Support\ClinicalRules;

use App\Enums\VitalSignType;
use App\Support\ClinicalFormCatalog;

/**
 * Riesgo de diabetes elevado que además tiene respaldo en otra señal.
 *
 * Un tamizaje elevado por sí solo ya trae su propia conducta sugerida en el
 * instrumento. Esta regla se dispara cuando ese riesgo coincide con una
 * glucemia fuera de rango o con falta de adherencia, porque el cruce de dos
 * señales independientes es lo que cambia la prioridad de la revisión.
 */
final class HighDiabetesRiskWithEvidence implements ClinicalRule
{
    public function key(): string
    {
        return 'riesgo_diabetes_con_evidencia';
    }

    public function evaluate(PatientSignals $signals): ?Recommendation
    {
        $screening = $signals->diabetesScreening;

        if (! $screening || ! in_array($screening->risk_level, (array) config('clinical_support.diabetes.escalating_levels'), true)) {
            return null;
        }

        $glucose = $signals->latestOutOfRange(VitalSignType::Glucose);
        $nonAdherent = in_array(
            $signals->adherence?->risk_level,
            (array) config('clinical_support.adherence.concerning_levels'),
            true,
        );

        // Sin una segunda señal esto es solo el resultado del tamizaje, que el
        // propio instrumento ya comunica al aplicarse.
        if (! $glucose && ! $nonAdherent) {
            return null;
        }

        $evidencia = $glucose
            ? sprintf(
                'una glucemia de %s %s registrada el %s',
                rtrim(rtrim((string) $glucose->value, '0'), '.'),
                $glucose->unit,
                $glucose->recorded_at->locale('es')->isoFormat('D [de] MMMM'),
            )
            : 'un resultado de no adherencia en el test de Morisky-Green';

        return new Recommendation(
            ruleKey: $this->key(),
            priority: Priority::High,
            title: 'Riesgo de diabetes con señal de respaldo',
            reason: sprintf(
                'El tamizaje de riesgo de diabetes dio %s (puntaje %d) el %s, y se cruza con %s.',
                mb_strtolower($screening->risk_level),
                (int) $screening->score,
                $screening->created_at->locale('es')->isoFormat('D [de] MMMM'),
                $evidencia,
            ),
            // La conducta sugerida sale del propio instrumento, no de esta regla.
            action: ClinicalFormCatalog::adviceFor('tamizaje_diabetes', $screening->risk_level)
                ?? 'Valorar al paciente según el protocolo de la IPS.',
        );
    }
}
