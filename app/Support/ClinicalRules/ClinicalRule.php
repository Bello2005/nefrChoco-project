<?php

namespace App\Support\ClinicalRules;

/**
 * Contrato de una regla de apoyo a decisiones clínicas.
 *
 * Una regla lee señales que el sistema YA calculó (nivel de riesgo de un
 * instrumento, evaluación de un signo vital contra su rango, diagnóstico de la
 * historia) y decide si amerita llamar la atención del profesional. Nunca
 * recalcula riesgo ni inventa umbrales propios.
 */
interface ClinicalRule
{
    /** Identificador estable de la regla, para poder rastrear qué se disparó. */
    public function key(): string;

    /** Devuelve la recomendación si la regla aplica, o null si no. */
    public function evaluate(PatientSignals $signals): ?Recommendation;
}
