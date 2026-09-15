<?php

namespace App\Services;

use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicalFormCatalog;

class ClinicalFormService
{
    public function create(Patient $patient, User $professional, string $formType, array $answers): ClinicalForm
    {
        $score = ClinicalFormCatalog::score($formType, $answers);

        return $patient->clinicalForms()->create([
            'recorded_by' => $professional->id,
            'form_type' => $formType,
            'answers' => $answers,
            'score' => $score['total'] ?? null,
            'risk_level' => $score['level'] ?? null,
        ]);
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
