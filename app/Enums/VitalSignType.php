<?php

namespace App\Enums;

enum VitalSignType: string
{
    case BloodPressure = 'presion_arterial';
    case BloodPressureDiastolic = 'presion_diastolica';
    case HeartRate = 'frecuencia_cardiaca';
    case Weight = 'peso';
    case Temperature = 'temperatura';
    case OxygenSaturation = 'saturacion_oxigeno';
    case Glucose = 'glucemia';

    public function label(): string
    {
        return match ($this) {
            self::BloodPressure => 'Presión arterial (sistólica)',
            self::BloodPressureDiastolic => 'Presión arterial (diastólica)',
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
            self::BloodPressure => 'Presión sistólica',
            self::BloodPressureDiastolic => 'Presión diastólica',
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
            self::BloodPressure, self::BloodPressureDiastolic => 'mmHg',
            self::HeartRate => 'lpm',
            self::Weight => 'kg',
            self::Temperature => '°C',
            self::OxygenSaturation => '%',
            self::Glucose => 'mg/dL',
        };
    }

    /**
     * Rango de referencia para adultos.
     *
     * Vive en config/vital_signs.php y no aquí: son umbrales clínicos que la
     * IPS debe poder revisar sin tocar código.
     *
     * @return array{min: float, max: float}
     */
    public function referenceRange(): array
    {
        $range = config("vital_signs.reference_ranges.{$this->value}");

        return [
            'min' => (float) $range['min'],
            'max' => (float) $range['max'],
        ];
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

    /**
     * Tipos que se eligen directamente en un formulario.
     *
     * La diastólica queda fuera a propósito: no se registra sola, sino junto a
     * la sistólica en un mismo envío, así que ofrecerla como opción suelta solo
     * confundiría a quien digita.
     *
     * La presión arterial viaja con su acompañante en `companion`, para que el
     * formulario pueda pedir las dos cifras y avisar si alguna sale de rango
     * sin tener que repetir los umbrales en el frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'unit' => $type->unit(),
                'min' => $type->referenceRange()['min'],
                'max' => $type->referenceRange()['max'],
                'companion' => $type === self::BloodPressure ? [
                    'value' => self::BloodPressureDiastolic->value,
                    'label' => self::BloodPressureDiastolic->label(),
                    'unit' => self::BloodPressureDiastolic->unit(),
                    'min' => self::BloodPressureDiastolic->referenceRange()['min'],
                    'max' => self::BloodPressureDiastolic->referenceRange()['max'],
                ] : null,
            ],
            array_filter(self::cases(), fn (self $type) => $type !== self::BloodPressureDiastolic),
        ));
    }
}
