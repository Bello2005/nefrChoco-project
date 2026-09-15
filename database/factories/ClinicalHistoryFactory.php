<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'medical_history' => $this->faker->paragraph(),
            'ecnt_diagnosis' => $this->faker->randomElement(['Hipertensión arterial', 'Diabetes mellitus tipo 2', 'Enfermedad renal crónica']),
            'allergies' => $this->faker->optional()->words(3, true),
            'current_medication' => $this->faker->optional()->words(4, true),
        ];
    }
}
