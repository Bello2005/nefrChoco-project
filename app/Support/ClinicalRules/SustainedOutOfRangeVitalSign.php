<?php

namespace App\Support\ClinicalRules;

use App\Models\VitalSign;

/**
 * Varias mediciones seguidas fuera del rango de referencia.
 *
 * El panel de telemonitoreo ya muestra lecturas sueltas fuera de rango. Esta
 * regla existe para separar el ruido de la tendencia: lo que amerita revisión
 * no es una lectura alta aislada, sino varias seguidas sin volver a la normalidad.
 */
final class SustainedOutOfRangeVitalSign implements ClinicalRule
{
    public function key(): string
    {
        return 'signo_vital_sostenido_fuera_de_rango';
    }

    public function evaluate(PatientSignals $signals): ?Recommendation
    {
        $minimum = (int) config('clinical_support.vital_signs.sustained_readings');

        foreach ($signals->typesWithReadings() as $type) {
            $streak = $signals->outOfRangeStreak($type);

            if ($streak->count() < $minimum) {
                continue;
            }

            $latest = $streak->first();
            $valores = $streak
                ->map(fn (VitalSign $sign) => rtrim(rtrim((string) $sign->value, '0'), '.').' '.$sign->unit)
                ->implode(', ');

            return new Recommendation(
                ruleKey: $this->key(),
                priority: Priority::High,
                title: $type->label().' sostenida fuera de rango',
                reason: sprintf(
                    'Las últimas %d mediciones de %s quedaron fuera del rango de referencia (%s–%s %s): %s. La más reciente es del %s.',
                    $streak->count(),
                    mb_strtolower($type->label()),
                    $type->referenceRange()['min'],
                    $type->referenceRange()['max'],
                    $type->unit(),
                    $valores,
                    $latest->recorded_at->locale('es')->isoFormat('D [de] MMMM'),
                ),
                action: 'Revisar el telemonitoreo del paciente y definir si requiere control presencial o ajuste de tratamiento.',
            );
        }

        return null;
    }
}
