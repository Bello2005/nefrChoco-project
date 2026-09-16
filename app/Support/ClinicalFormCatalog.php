<?php

namespace App\Support;

use App\Enums\EcntCategory;

/**
 * Catálogo de instrumentos clínicos de tamizaje y seguimiento.
 *
 * Las plantillas viven en código (no en base de datos) porque son instrumentos
 * validados que cambian con evidencia clínica, no con configuración del cliente:
 * versionarlos en git deja trazabilidad de qué instrumento se aplicó y cuándo.
 */
class ClinicalFormCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'tamizaje_diabetes' => [
                'key' => 'tamizaje_diabetes',
                'name' => 'Tamizaje de riesgo de diabetes',
                'description' => 'Instrumento tipo FINDRISC adaptado para estimar el riesgo de desarrollar diabetes tipo 2 en los próximos 10 años.',
                'category' => EcntCategory::Diabetes->value,
                'fields' => [
                    [
                        'key' => 'edad',
                        'label' => '¿Cuál es su edad?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'menor_45', 'label' => 'Menor de 45 años', 'score' => 0],
                            ['value' => '45_54', 'label' => 'Entre 45 y 54 años', 'score' => 2],
                            ['value' => '55_64', 'label' => 'Entre 55 y 64 años', 'score' => 3],
                            ['value' => 'mayor_64', 'label' => 'Mayor de 64 años', 'score' => 4],
                        ],
                    ],
                    [
                        'key' => 'imc',
                        'label' => 'Índice de masa corporal',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'normal', 'label' => 'Menor a 25 (normal)', 'score' => 0],
                            ['value' => 'sobrepeso', 'label' => 'Entre 25 y 30 (sobrepeso)', 'score' => 1],
                            ['value' => 'obesidad', 'label' => 'Mayor a 30 (obesidad)', 'score' => 3],
                        ],
                    ],
                    [
                        'key' => 'perimetro_abdominal',
                        'label' => 'Perímetro abdominal',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'normal', 'label' => 'Normal (H <94 cm / M <80 cm)', 'score' => 0],
                            ['value' => 'elevado', 'label' => 'Elevado (H 94-102 cm / M 80-88 cm)', 'score' => 3],
                            ['value' => 'muy_elevado', 'label' => 'Muy elevado (H >102 cm / M >88 cm)', 'score' => 4],
                        ],
                    ],
                    [
                        'key' => 'actividad_fisica',
                        'label' => '¿Realiza al menos 30 minutos de actividad física al día?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'si', 'label' => 'Sí', 'score' => 0],
                            ['value' => 'no', 'label' => 'No', 'score' => 2],
                        ],
                    ],
                    [
                        'key' => 'frutas_verduras',
                        'label' => '¿Con qué frecuencia consume frutas y verduras?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'diario', 'label' => 'Todos los días', 'score' => 0],
                            ['value' => 'ocasional', 'label' => 'No todos los días', 'score' => 1],
                        ],
                    ],
                    [
                        'key' => 'antecedente_familiar',
                        'label' => '¿Tiene familiares diagnosticados con diabetes?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'no', 'label' => 'No', 'score' => 0],
                            ['value' => 'segundo_grado', 'label' => 'Abuelos, tíos o primos', 'score' => 3],
                            ['value' => 'primer_grado', 'label' => 'Padres, hermanos o hijos', 'score' => 5],
                        ],
                    ],
                    [
                        'key' => 'observaciones',
                        'label' => 'Observaciones del profesional',
                        'type' => 'textarea',
                        'optional' => true,
                    ],
                ],
                'scoring' => [
                    'enabled' => true,
                    'thresholds' => [
                        ['max' => 6, 'level' => 'bajo', 'label' => 'Riesgo bajo', 'tone' => 'success', 'advice' => 'Mantener hábitos saludables. Reevaluar en 12 meses.'],
                        ['max' => 11, 'level' => 'ligero', 'label' => 'Riesgo ligeramente elevado', 'tone' => 'info', 'advice' => 'Reforzar educación en alimentación y actividad física. Reevaluar en 12 meses.'],
                        ['max' => 14, 'level' => 'moderado', 'label' => 'Riesgo moderado', 'tone' => 'warning', 'advice' => 'Solicitar glucemia en ayunas y programar seguimiento a 6 meses.'],
                        ['max' => 99, 'level' => 'alto', 'label' => 'Riesgo alto', 'tone' => 'destructive', 'advice' => 'Derivar a valoración médica prioritaria con glucemia y hemoglobina glicosilada.'],
                    ],
                ],
            ],

            'adherencia_tratamiento' => [
                'key' => 'adherencia_tratamiento',
                'name' => 'Adherencia al tratamiento',
                'description' => 'Test de Morisky-Green-Levine para valorar el cumplimiento del tratamiento farmacológico.',
                'category' => EcntCategory::GeneralPrevention->value,
                'fields' => [
                    [
                        'key' => 'olvida_medicamento',
                        'label' => '¿Olvida alguna vez tomar su medicamento?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'no', 'label' => 'No', 'score' => 0],
                            ['value' => 'si', 'label' => 'Sí', 'score' => 1],
                        ],
                    ],
                    [
                        'key' => 'toma_hora_indicada',
                        'label' => '¿Toma los medicamentos a la hora indicada?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'si', 'label' => 'Sí', 'score' => 0],
                            ['value' => 'no', 'label' => 'No', 'score' => 1],
                        ],
                    ],
                    [
                        'key' => 'suspende_si_mejora',
                        'label' => 'Cuando se encuentra bien, ¿deja de tomarlos?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'no', 'label' => 'No', 'score' => 0],
                            ['value' => 'si', 'label' => 'Sí', 'score' => 1],
                        ],
                    ],
                    [
                        'key' => 'suspende_si_mal',
                        'label' => 'Si alguna vez le sientan mal, ¿deja de tomarlos?',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'no', 'label' => 'No', 'score' => 0],
                            ['value' => 'si', 'label' => 'Sí', 'score' => 1],
                        ],
                    ],
                    [
                        'key' => 'barreras',
                        'label' => 'Barreras identificadas (distancia, costo, disponibilidad)',
                        'type' => 'textarea',
                        'optional' => true,
                    ],
                ],
                'scoring' => [
                    'enabled' => true,
                    'thresholds' => [
                        ['max' => 0, 'level' => 'adherente', 'label' => 'Paciente adherente', 'tone' => 'success', 'advice' => 'Reforzar positivamente y mantener el esquema actual.'],
                        ['max' => 2, 'level' => 'parcial', 'label' => 'Adherencia parcial', 'tone' => 'warning', 'advice' => 'Identificar barreras concretas y simplificar el esquema si es posible.'],
                        ['max' => 4, 'level' => 'no_adherente', 'label' => 'No adherente', 'tone' => 'destructive', 'advice' => 'Intervención educativa dirigida y seguimiento telefónico en 15 días.'],
                    ],
                ],
            ],

            'seguimiento_hipertension' => [
                'key' => 'seguimiento_hipertension',
                'name' => 'Seguimiento de hipertensión arterial',
                'description' => 'Control periódico de cifras tensionales y hábitos en pacientes con diagnóstico de HTA.',
                'category' => EcntCategory::Hypertension->value,
                'fields' => [
                    ['key' => 'presion_sistolica', 'label' => 'Presión sistólica (mmHg)', 'type' => 'number', 'min' => 60, 'max' => 260],
                    ['key' => 'presion_diastolica', 'label' => 'Presión diastólica (mmHg)', 'type' => 'number', 'min' => 30, 'max' => 160],
                    [
                        'key' => 'consumo_sal',
                        'label' => 'Consumo de sal referido',
                        'type' => 'select',
                        'options' => [
                            ['value' => 'bajo', 'label' => 'Bajo'],
                            ['value' => 'moderado', 'label' => 'Moderado'],
                            ['value' => 'alto', 'label' => 'Alto'],
                        ],
                    ],
                    [
                        'key' => 'sintomas',
                        'label' => 'Síntomas referidos desde el último control',
                        'type' => 'textarea',
                        'optional' => true,
                    ],
                    [
                        'key' => 'plan',
                        'label' => 'Plan de manejo',
                        'type' => 'textarea',
                        'optional' => true,
                    ],
                ],
                'scoring' => ['enabled' => false],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return self::find($key) !== null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Reglas de validación derivadas de la propia plantilla, para que el
     * formulario y su validación nunca se desincronicen.
     *
     * @return array<string, mixed>
     */
    public static function validationRules(string $key): array
    {
        $template = self::find($key);

        if (! $template) {
            return [];
        }

        $rules = [];

        foreach ($template['fields'] as $field) {
            $required = ($field['optional'] ?? false) ? 'nullable' : 'required';

            $rules["answers.{$field['key']}"] = match ($field['type']) {
                'select' => [$required, 'string', 'in:'.implode(',', array_column($field['options'], 'value'))],
                'number' => [$required, 'numeric', 'min:'.($field['min'] ?? 0), 'max:'.($field['max'] ?? 10000)],
                default => [$required, 'string', 'max:2000'],
            };
        }

        return $rules;
    }

    /**
     * Conducta sugerida que el propio instrumento define para un nivel de riesgo.
     *
     * La expone el apoyo a decisiones clínicas para no inventar conductas por su
     * cuenta: lo que se recomienda hacer ante un riesgo alto ya está escrito en
     * el instrumento validado, y debe salir de una sola fuente.
     */
    public static function adviceFor(string $key, ?string $level): ?string
    {
        if ($level === null) {
            return null;
        }

        foreach (self::find($key)['scoring']['thresholds'] ?? [] as $threshold) {
            if ($threshold['level'] === $level) {
                return $threshold['advice'];
            }
        }

        return null;
    }

    /**
     * Calcula el puntaje del instrumento y su interpretación clínica.
     *
     * @param  array<string, mixed>  $answers
     * @return array{total: int, max: int, level: string, label: string, tone: string, advice: string}|null
     */
    public static function score(string $key, array $answers): ?array
    {
        $template = self::find($key);

        if (! $template || ! ($template['scoring']['enabled'] ?? false)) {
            return null;
        }

        $total = 0;
        $max = 0;

        foreach ($template['fields'] as $field) {
            if ($field['type'] !== 'select' || ! isset($field['options'][0]['score'])) {
                continue;
            }

            $scores = array_column($field['options'], 'score', 'value');
            $max += max($scores);

            $answer = $answers[$field['key']] ?? null;
            $total += $scores[$answer] ?? 0;
        }

        foreach ($template['scoring']['thresholds'] as $threshold) {
            if ($total <= $threshold['max']) {
                return [
                    'total' => $total,
                    'max' => $max,
                    'level' => $threshold['level'],
                    'label' => $threshold['label'],
                    'tone' => $threshold['tone'],
                    'advice' => $threshold['advice'],
                ];
            }
        }

        return null;
    }
}
