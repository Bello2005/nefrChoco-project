<?php

namespace App\Support\ClinicalRules;

use App\Models\ClinicalForm;

/**
 * Deterioro de la función renal.
 *
 * Dos ramas distintas. Una mira dónde está el paciente hoy: una categoría KDIGO
 * avanzada amerita valoración especializada sin esperar a ver una tendencia.
 * La otra mira hacia dónde va: una caída sostenida de la TFGe entre controles
 * importa aunque las cifras todavía no sean alarmantes.
 *
 * Ninguna recalcula la TFGe: usa la que quedó congelada con cada formulario.
 */
// TODO doc: falta esta regla en el diagrama de apoyo a decisiones de
// arquitectura.md y en la sección del profesional de guia-usuario.md.
final class KidneyFunctionDecline implements ClinicalRule
{
    public function key(): string
    {
        return 'deterioro_funcion_renal';
    }

    public function evaluate(PatientSignals $signals): ?Recommendation
    {
        $ultimo = $signals->latestRenalControl();

        if (! $ultimo) {
            return null;
        }

        return $this->porCategoriaAvanzada($signals, $ultimo)
            ?? $this->porProgresionRapida($signals, $ultimo);
    }

    /** Categoría G4/G5 o albuminuria A3: no necesita comparar con un control previo. */
    private function porCategoriaAvanzada(PatientSignals $signals, ClinicalForm $ultimo): ?Recommendation
    {
        $gfrAvanzada = in_array($ultimo->kdigo_g, (array) config('clinical_support.kidney.referral_gfr_categories'), true);
        $albuminuriaAlta = in_array($ultimo->kdigo_a, (array) config('clinical_support.kidney.referral_albuminuria_categories'), true);

        if (! $gfrAvanzada && ! $albuminuriaAlta) {
            return null;
        }

        $motivo = sprintf(
            'El control renal del %s dio una TFGe de %d mL/min/1,73 m², categoría %s',
            $signals->labDate($ultimo)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            (int) $ultimo->egfr,
            $ultimo->kdigo_g,
        );

        $motivo .= $ultimo->kdigo_a
            ? sprintf(', con albuminuria %s.', $ultimo->kdigo_a)
            : '.';

        return new Recommendation(
            ruleKey: $this->key(),
            priority: Priority::High,
            title: 'Función renal en categoría avanzada',
            reason: $motivo,
            action: 'Sugerir valoración por nefrología según el protocolo de la IPS.',
        );
    }

    /**
     * Caída anualizada de la TFGe entre los dos controles más recientes.
     *
     * Se exige un intervalo mínimo entre laboratorios: con dos exámenes casi
     * simultáneos, anualizar la diferencia produciría una pendiente enorme a
     * partir de lo que probablemente sea variabilidad de medición.
     */
    private function porProgresionRapida(PatientSignals $signals, ClinicalForm $ultimo): ?Recommendation
    {
        $previo = $signals->previousRenalControl();

        if (! $previo) {
            return null;
        }

        $dias = (int) $signals->labDate($previo)->diffInDays($signals->labDate($ultimo), true);

        if ($dias < (int) config('clinical_support.kidney.minimum_days_between_labs')) {
            return null;
        }

        $caida = (int) $previo->egfr - (int) $ultimo->egfr;

        // Una TFGe que mejora o se mantiene no es progresión.
        if ($caida <= 0) {
            return null;
        }

        $caidaAnual = $caida * 365 / $dias;
        $umbral = (float) config('clinical_support.kidney.rapid_progression_per_year');

        if ($caidaAnual <= $umbral) {
            return null;
        }

        return new Recommendation(
            ruleKey: $this->key(),
            priority: Priority::Medium,
            title: 'Caída acelerada de la función renal',
            reason: sprintf(
                'La TFGe pasó de %d mL/min/1,73 m² el %s a %d el %s: %d puntos en %d días, equivalente a %s por año, por encima de los %s que KDIGO considera progresión rápida.',
                (int) $previo->egfr,
                $signals->labDate($previo)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                (int) $ultimo->egfr,
                $signals->labDate($ultimo)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                $caida,
                $dias,
                number_format($caidaAnual, 1, ',', '.'),
                number_format($umbral, 0, ',', '.'),
            ),
            action: 'Revisar la tendencia con el paciente y definir si amerita un control más frecuente o derivación a nefrología.',
        );
    }
}
