<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Medico = 'medico';
    case Paciente = 'paciente';

    // Futuro: case Cuidador = 'cuidador';

    /**
     * Personal de la IPS: las cuentas con acceso a datos clínicos de terceros.
     * Su correo es institucional y lo administra la IPS, no el propio usuario.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Admin, self::Medico];
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Medico => 'Médico',
            self::Paciente => 'Paciente',
        };
    }
}
