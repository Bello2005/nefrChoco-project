<?php

namespace App\Enums;

/**
 * Sexo biológico registrado en la ficha del paciente.
 *
 * Se pide exclusivamente porque las fórmulas de función renal lo necesitan como
 * variable: CKD-EPI 2021 aplica un coeficiente distinto según el sexo. No es un
 * campo de identidad de género, y por eso solo admite los dos valores que la
 * fórmula contempla, sin inferirlo de ningún otro dato de la ficha.
 */
enum BiologicalSex: string
{
    case Female = 'femenino';
    case Male = 'masculino';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $sex) => ['value' => $sex->value, 'label' => $sex->label()],
            self::cases(),
        );
    }
}
