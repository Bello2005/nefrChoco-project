<?php

namespace App\Support;

/**
 * System Usability Scale (Brooke, 1996).
 *
 * Diez afirmaciones que se responden en una escala de 1 a 5, donde 1 es "muy en
 * desacuerdo" y 5 es "muy de acuerdo". Los ítems alternan sentido a propósito:
 * los impares están redactados en positivo y los pares en negativo, para que
 * quien responde tenga que leer cada frase en vez de marcar toda una columna.
 *
 * Esa alternancia es también la razón de la fórmula: cada ítem aporta de 0 a 4
 * puntos, pero los impares cuentan `respuesta - 1` y los pares `5 - respuesta`.
 * La suma va de 0 a 40 y se multiplica por 2.5 para dejarla en una escala de 0 a
 * 100. **No es un porcentaje**: un 68 es el promedio de referencia de la escala,
 * no "el 68% de los usuarios".
 *
 * Los enunciados están adaptados al castellano y al contexto de la plataforma,
 * conservando el sentido y el orden del instrumento original.
 */
class SusInstrument
{
    public const ITEM_COUNT = 10;

    public const MIN_ANSWER = 1;

    public const MAX_ANSWER = 5;

    /** Promedio de referencia publicado para la escala; sirve para situar un puntaje. */
    public const REFERENCE_AVERAGE = 68.0;

    /**
     * Enunciados en orden. La posición importa: define si el ítem puntúa en
     * positivo o en negativo.
     *
     * @return array<int, string>
     */
    public static function statements(): array
    {
        return [
            1 => 'Creo que usaría esta plataforma con frecuencia.',
            2 => 'Encontré la plataforma innecesariamente compleja.',
            3 => 'Me pareció fácil de usar.',
            4 => 'Creo que necesitaría ayuda de alguien con conocimientos técnicos para poder usarla.',
            5 => 'Las funciones de la plataforma están bien integradas entre sí.',
            6 => 'Me pareció que había demasiada inconsistencia en la plataforma.',
            7 => 'Imagino que la mayoría de la gente aprendería a usarla muy rápido.',
            8 => 'Me resultó muy incómoda de usar.',
            9 => 'Me sentí con confianza al usarla.',
            10 => 'Necesité aprender muchas cosas antes de poder manejarme con la plataforma.',
        ];
    }

    /**
     * Puntaje SUS de 0 a 100, con un decimal.
     *
     * @param  array<int, int>  $answers  Respuestas de 1 a 5, indexadas por número de ítem (1..10).
     */
    public static function score(array $answers): float
    {
        $total = 0;

        foreach (range(1, self::ITEM_COUNT) as $item) {
            $answer = $answers[$item];

            // Los impares suman lo que sobra por encima del mínimo; los pares, lo
            // que falta para el máximo. En ambos casos el aporte va de 0 a 4.
            $total += $item % 2 === 1
                ? $answer - self::MIN_ANSWER
                : self::MAX_ANSWER - $answer;
        }

        return round($total * 2.5, 1);
    }

    /**
     * Lectura cualitativa del puntaje, según los rangos de uso habitual de la
     * escala. Es una ayuda para leer el reporte, no un resultado del instrumento.
     */
    public static function interpret(float $score): string
    {
        return match (true) {
            $score >= 85.0 => 'Excelente',
            $score >= 72.5 => 'Buena',
            $score >= self::REFERENCE_AVERAGE => 'Aceptable',
            $score >= 51.0 => 'Pobre',
            default => 'Inaceptable',
        };
    }
}
