<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentScheduledNotification;

class AppointmentService
{
    public function __construct(
        private readonly TeleconsultationService $teleconsultationService,
    ) {}

    public function create(array $data): Appointment
    {
        $appointment = Appointment::create([
            ...$data,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        if ($appointment->type === Appointment::TYPE_TELECONSULTATION) {
            $this->teleconsultationService->createForAppointment($appointment);
        }

        // Solo si el paciente tiene cuenta propia: muchas fichas del programa
        // corresponden a personas sin acceso a la plataforma.
        $appointment->patient->user?->notify(new AppointmentScheduledNotification($appointment));

        return $appointment;
    }

    public function update(Appointment $appointment, array $data): Appointment
    {
        $appointment->update($data);

        if ($appointment->type === Appointment::TYPE_TELECONSULTATION) {
            $this->teleconsultationService->findOrCreateForAppointment($appointment);
        }

        return $appointment;
    }
}
