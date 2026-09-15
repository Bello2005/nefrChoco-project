<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Medico = 'medico';
    case Paciente = 'paciente';

    // Futuro: case Cuidador = 'cuidador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Medico => 'Médico',
            self::Paciente => 'Paciente',
        };
    }
}
