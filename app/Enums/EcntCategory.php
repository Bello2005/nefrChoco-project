<?php

namespace App\Enums;

enum EcntCategory: string
{
    case Diabetes = 'diabetes';
    case Hypertension = 'hipertension';
    case ChronicKidneyDisease = 'enfermedad_renal';
    case Obesity = 'obesidad';
    case Cardiovascular = 'cardiovascular';
    case GeneralPrevention = 'prevencion_general';

    public function label(): string
    {
        return match ($this) {
            self::Diabetes => 'Diabetes',
            self::Hypertension => 'Hipertensión arterial',
            self::ChronicKidneyDisease => 'Enfermedad renal crónica',
            self::Obesity => 'Obesidad y nutrición',
            self::Cardiovascular => 'Riesgo cardiovascular',
            self::GeneralPrevention => 'Prevención general',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $category) => [
            'value' => $category->value,
            'label' => $category->label(),
        ], self::cases());
    }
}
