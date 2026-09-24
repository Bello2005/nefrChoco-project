<?php

namespace Database\Factories;

use App\Enums\BiologicalSex;
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
            'teleconsultation_consent_accepted_at' => now(),
            'teleconsultation_consent_version' => config('privacy.teleconsultation_consent_version'),
            'first_name' => $firstName = $this->faker->firstName(),
            'first_surname' => $firstSurname = $this->faker->lastName(),
            'second_surname' => $secondSurname = $this->faker->lastName(),
            'full_name' => "{$firstName} {$firstSurname} {$secondSurname}",
            'document_type' => 'CC',
            'document_number' => $this->faker->unique()->numerify('##########'),
            'birth_date' => $this->faker->date(),
            // Solo los dos valores con los que se calcula la TFGe: indeterminado y
            // desconocido se piden explícitamente en las pruebas que los usan.
            'biological_sex' => $this->faker->randomElement([BiologicalSex::Female->value, BiologicalSex::Male->value]),
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

    /** Ficha anterior a que el dato existiera, como las ya registradas. */
    public function withoutBiologicalSex(): static
    {
        return $this->state(fn () => ['biological_sex' => null]);
    }

    public function withoutTeleconsultationConsent(): static
    {
        return $this->state(fn () => [
            'teleconsultation_consent_accepted_at' => null,
            'teleconsultation_consent_version' => null,
        ]);
    }
}
