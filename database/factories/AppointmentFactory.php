<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
            'status' => Appointment::STATUS_SCHEDULED,
            'type' => Appointment::TYPE_IN_PERSON,
        ];
    }
}
