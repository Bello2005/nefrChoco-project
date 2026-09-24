<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentScheduledNotification;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private readonly TeleconsultationService $teleconsultationService,
        private readonly AttentionRecordService $attentionRecord,
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

    /**
     * Si la cita pasa a completada, se guarda también el registro de la
     * atención, en la misma transacción. Después ya no se puede editar
     * (AppointmentPolicy::update exige que no esté atendida).
     */
    public function update(Appointment $appointment, array $data, ?User $author = null): Appointment
    {
        $appointmentFields = array_intersect_key($data, array_flip(['patient_id', 'scheduled_at', 'type', 'status']));
        $closes = ($data['status'] ?? null) === Appointment::STATUS_COMPLETED && $appointment->status !== Appointment::STATUS_COMPLETED;

        DB::transaction(function () use ($appointment, $appointmentFields, $data, $author, $closes) {
            $appointment->update($appointmentFields);

            if ($closes && $author !== null) {
                $this->attentionRecord->record($appointment, $author, $data);
            }
        });

        if ($appointment->type === Appointment::TYPE_TELECONSULTATION) {
            $this->teleconsultationService->findOrCreateForAppointment($appointment);
        }

        return $appointment;
    }
}
