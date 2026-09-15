<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            // Un paciente activo del programa ya autorizó el tratamiento de sus
            // datos; el caso sin autorización se pide explícitamente con
            // withoutConsent() en las pruebas que lo necesitan.
            'consent_accepted_at' => now(),
            'consent_version' => config('privacy.consent_version'),
            'full_name' => $this->faker->name(),
            'document_type' => 'CC',
            'document_number' => $this->faker->unique()->numerify('##########'),
            'birth_date' => $this->faker->date(),
            'municipality' => $this->faker->randomElement(['Quibdó', 'Istmina', 'Condoto', 'Tadó', 'Nuquí']),
            'phone' => $this->faker->numerify('3#########'),
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->numerify('3#########'),
        ];
    }

    public function withoutConsent(): static
    {
        return $this->state(fn () => [
            'consent_accepted_at' => null,
            'consent_version' => null,
        ]);
    }
}
