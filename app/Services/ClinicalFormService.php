<?php

namespace App\Services;

use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicalFormCatalog;
use Carbon\CarbonImmutable;

class ClinicalFormService
{
    public const RENAL_FORM = 'seguimiento_erc';

    public function __construct(
        private readonly EgfrCalculator $egfrCalculator,
    ) {}

    public function create(Patient $patient, User $professional, string $formType, array $answers): ClinicalForm
    {
        $score = ClinicalFormCatalog::score($formType, $answers);

        return $patient->clinicalForms()->create([
            'recorded_by' => $professional->id,
            'form_type' => $formType,
            'answers' => $answers,
            'score' => $score['total'] ?? null,
            'risk_level' => $score['level'] ?? null,
            ...$this->renalResult($patient, $formType, $answers),
        ]);
    }

    /**
     * TFGe y categorías KDIGO del seguimiento renal.
     *
     * Se calculan una sola vez, al guardar, y quedan congeladas con el
     * formulario: si la ecuación cambia después, el histórico debe seguir
     * mostrando lo que se concluyó ese día.
     *
     * @return array<string, mixed>
     */
    private function renalResult(Patient $patient, string $formType, array $answers): array
    {
        // Sin sexo biológico, o con indeterminado/desconocido, CKD-EPI no aplica.
        if ($formType !== self::RENAL_FORM || ! $patient->biological_sex?->supportsEgfr()) {
            return [];
        }

        $labDate = CarbonImmutable::parse($answers['fecha_laboratorio']);

        // La edad se toma a la fecha del laboratorio y no a hoy: un resultado
        // puede cargarse meses después de la toma, y la TFG corresponde al
        // momento del examen.
        $age = (int) $patient->birth_date->diffInYears($labDate, true);

        $egfr = $this->egfrCalculator->estimate((float) $answers['creatinina'], $age, $patient->biological_sex);

        $acr = $answers['relacion_albumina_creatinina'] ?? null;

        return [
            'egfr' => $egfr,
            'kdigo_g' => $this->egfrCalculator->gCategory($egfr),
            'kdigo_a' => $this->egfrCalculator->aCategory($acr === null || $acr === '' ? null : (float) $acr),
        ];
    }

    /**
     * Respuestas legibles para mostrar en pantalla: convierte los valores
     * almacenados en las etiquetas que vio quien diligenció el instrumento.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function readableAnswers(ClinicalForm $form): array
    {
        $template = ClinicalFormCatalog::find($form->form_type);

        if (! $template) {
            return [];
        }

        $readable = [];

        foreach ($template['fields'] as $field) {
            $answer = $form->answers[$field['key']] ?? null;

            if ($answer === null || $answer === '') {
                continue;
            }

            $value = $field['type'] === 'select'
                ? (array_column($field['options'], 'label', 'value')[$answer] ?? $answer)
                : (string) $answer;

            $readable[] = ['label' => $field['label'], 'value' => $value];
        }

        return $readable;
    }
}
