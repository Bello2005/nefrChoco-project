<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalFormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'recorded_by' => User::factory(),
            'form_type' => 'adherencia_tratamiento',
            'answers' => [
                'olvida_medicamento' => 'no',
                'toma_hora_indicada' => 'si',
                'suspende_si_mejora' => 'no',
                'suspende_si_mal' => 'no',
            ],
            'score' => 0,
            'risk_level' => 'adherente',
        ];
    }
}
