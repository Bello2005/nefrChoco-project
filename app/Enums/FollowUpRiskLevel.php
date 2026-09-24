<?php

namespace App\Enums;

/**
 * Nivel de riesgo para el seguimiento remoto (Res. 1644 de 2026, art. 19 par. 1).
 *
 * Lo asigna el médico. No es un diagnóstico ni sale de un instrumento validado
 * (FINDRISC y Morisky miden otras cosas): solo decide cada cuánto se espera
 * una medición del paciente, según config/vital_signs.php.
 *
 * TODO: validar con la médica de la IPS los niveles y sus nombres.
 */
enum FollowUpRiskLevel: string
{
    case Low = 'bajo';
    case Medium = 'medio';
    case High = 'alto';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Riesgo bajo',
            self::Medium => 'Riesgo medio',
            self::High => 'Riesgo alto',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $level) => ['value' => $level->value, 'label' => $level->label()], self::cases());
    }
}
