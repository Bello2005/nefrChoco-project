<?php

namespace App\Enums;

enum VitalSignType: string
{
    case BloodPressure = 'presion_arterial';
    case HeartRate = 'frecuencia_cardiaca';
    case Weight = 'peso';
    case Temperature = 'temperatura';
    case OxygenSaturation = 'saturacion_oxigeno';
    case Glucose = 'glucemia';

    public function label(): string
    {
        return match ($this) {
            self::BloodPressure => 'Presión arterial (sistólica)',
            self::HeartRate => 'Frecuencia cardíaca',
            self::Weight => 'Peso',
            self::Temperature => 'Temperatura',
            self::OxygenSaturation => 'Saturación de oxígeno',
            self::Glucose => 'Glucemia',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::BloodPressure => 'Presión',
            self::HeartRate => 'Pulso',
            self::Weight => 'Peso',
            self::Temperature => 'Temperatura',
            self::OxygenSaturation => 'Saturación',
            self::Glucose => 'Glucemia',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::BloodPressure => 'mmHg',
            self::HeartRate => 'lpm',
            self::Weight => 'kg',
            self::Temperature => '°C',
            self::OxygenSaturation => '%',
            self::Glucose => 'mg/dL',
        };
    }

    /**
     * Rango de referencia para adultos. Orienta el tamizaje en territorio;
     * no reemplaza el criterio clínico del profesional que evalúa al paciente.
     *
     * @return array{min: float, max: float}
     */
    public function referenceRange(): array
    {
        return match ($this) {
            self::BloodPressure => ['min' => 90, 'max' => 130],
            self::HeartRate => ['min' => 60, 'max' => 100],
            self::Weight => ['min' => 40, 'max' => 120],
            self::Temperature => ['min' => 36, 'max' => 37.5],
            self::OxygenSaturation => ['min' => 94, 'max' => 100],
            self::Glucose => ['min' => 70, 'max' => 140],
        };
    }

    /** Fuera del rango de referencia: 'bajo', 'alto' o 'normal'. */
    public function evaluate(float $value): string
    {
        $range = $this->referenceRange();

        return match (true) {
            $value < $range['min'] => 'bajo',
            $value > $range['max'] => 'alto',
            default => 'normal',
        };
    }

    /** @return array<int, array{value: string, label: string, unit: string, min: float, max: float}> */
    public static function options(): array
    {
        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'unit' => $type->unit(),
            'min' => $type->referenceRange()['min'],
            'max' => $type->referenceRange()['max'],
        ], self::cases());
    }
}
