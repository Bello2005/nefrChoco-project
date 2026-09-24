<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PractitionerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_type' => 'CC',
            'document_number' => $this->faker->unique()->numerify('##########'),
            'profession' => 'Medicina',
            'professional_registration' => $this->faker->numerify('RP-######'),
            'specialty' => null,
        ];
    }
}
