<?php

namespace Database\Factories;

use App\Models\Teleconsultation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeleconsultationClarificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teleconsultation_id' => Teleconsultation::factory(),
            'author_id' => User::factory(),
            'body' => $this->faker->sentence(),
        ];
    }
}
