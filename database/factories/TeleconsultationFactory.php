<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Teleconsultation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeleconsultationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'room_name' => 'nefrochoco-'.Str::uuid(),
            'status' => Teleconsultation::STATUS_PENDING,
            'notes' => null,
        ];
    }
}
