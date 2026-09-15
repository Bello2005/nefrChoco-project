<?php

namespace Database\Factories;

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VitalSignFactory extends Factory
{
    public function definition(): array
    {
        $type = VitalSignType::HeartRate;
        $range = $type->referenceRange();

        return [
            'patient_id' => Patient::factory(),
            'recorded_by' => User::factory(),
            'type' => $type->value,
            // Por defecto dentro del rango: los tests que necesitan una alerta
            // fijan el valor explícitamente.
            'value' => $this->faker->randomFloat(1, $range['min'], $range['max']),
            'unit' => $type->unit(),
            'recorded_at' => now(),
            'notes' => null,
        ];
    }

    public function ofType(VitalSignType $type, ?float $value = null): static
    {
        return $this->state(fn () => [
            'type' => $type->value,
            'unit' => $type->unit(),
            'value' => $value ?? $this->faker->randomFloat(1, $type->referenceRange()['min'], $type->referenceRange()['max']),
        ]);
    }
}
