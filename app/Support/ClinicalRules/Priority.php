<?php

namespace App\Support\ClinicalRules;

enum Priority: string
{
    case High = 'alta';
    case Medium = 'media';
    case Low = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Prioridad alta',
            self::Medium => 'Prioridad media',
            self::Low => 'Prioridad baja',
        };
    }

    /** Para ordenar: primero lo que más urge revisar. */
    public function weight(): int
    {
        return match ($this) {
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }
}
