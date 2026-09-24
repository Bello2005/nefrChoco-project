<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\TeleconsultationClarification;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeleconsultationService
{
    public function createForAppointment(Appointment $appointment): Teleconsultation
    {
        return $appointment->teleconsultation()->create([
            'room_name' => $this->generateRoomName(),
            'status' => Teleconsultation::STATUS_PENDING,
        ]);
    }

    public function findOrCreateForAppointment(Appointment $appointment): Teleconsultation
    {
        return $appointment->teleconsultation ?? $this->createForAppointment($appointment);
    }

    /**
     * Cierra la teleconsulta con sus notas clínicas.
     *
     * Cerrar la sala también completa la cita: son el mismo hecho asistencial y
     * dejarlos desalineados haría que la agenda reportara atenciones pendientes
     * que en realidad ya ocurrieron.
     *
     * Una nota cerrada no se reescribe: la auditoría guarda qué campos cambió
     * cada quien, no sus valores, así que la nota anterior se perdería sin
     * rastro. Se relee la fila con bloqueo porque el doble clic o una pestaña
     * vieja pueden llegar a la vez, y las dos verían la sala todavía abierta.
     * Para corregir una nota cerrada están las aclaraciones.
     *
     * @throws \DomainException si la teleconsulta ya estaba cerrada
     */
    public function complete(Teleconsultation $teleconsultation, string $notes, ?User $author = null, array $attention = []): Teleconsultation
    {
        return DB::transaction(function () use ($teleconsultation, $notes, $author, $attention) {
            $locked = Teleconsultation::whereKey($teleconsultation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Teleconsultation::STATUS_FINISHED) {
                throw new \DomainException('La teleconsulta ya estaba cerrada.');
            }

            $locked->update([
                'notes' => $notes,
                'status' => Teleconsultation::STATUS_FINISHED,
            ]);

            $locked->appointment->update(['status' => Appointment::STATUS_COMPLETED]);

            // En la misma transacción y bajo el mismo bloqueo: un doble envío no
            // puede dejar dos registros de la misma atención.
            if ($author !== null) {
                app(AttentionRecordService::class)->record($locked->appointment, $author, $attention);
            }

            return $locked;
        });
    }

    /**
     * Agrega una aclaración a la nota de una teleconsulta cerrada.
     *
     * La política ya lo revisó, pero se vuelve a comprobar aquí porque el
     * servicio es el que escribe: una aclaración sobre una sala abierta no
     * tiene nota que aclarar.
     *
     * @throws \DomainException si la teleconsulta todavía no está cerrada
     */
    public function addClarification(Teleconsultation $teleconsultation, User $author, string $body, ?array $correctedDiagnosis = null): TeleconsultationClarification
    {
        if ($teleconsultation->status !== Teleconsultation::STATUS_FINISHED) {
            throw new \DomainException('Solo se aclaran notas de teleconsultas cerradas.');
        }

        return DB::transaction(function () use ($teleconsultation, $author, $body, $correctedDiagnosis) {
            $clarification = new TeleconsultationClarification(['body' => $body]);
            $clarification->teleconsultation()->associate($teleconsultation);
            $clarification->author()->associate($author);
            $clarification->save();

            // Una aclaración puede traer el diagnóstico corregido: se agrega como
            // fila nueva que reemplaza a la anterior, sin tocarla.
            if ($correctedDiagnosis !== null) {
                app(AttentionRecordService::class)->addCorrection($clarification, $author, $correctedDiagnosis);
            }

            return $clarification;
        });
    }

    /**
     * Motivo por el que el paciente no puede entrar a la sala, o null si sí puede.
     *
     * Devuelve el texto y no un booleano porque en zonas con mala señal el
     * paciente necesita saber qué hacer: esperar, reintentar o llamar a la IPS.
     */
    public function joinBlockedReason(Appointment $appointment): ?string
    {
        if ($appointment->type !== Appointment::TYPE_TELECONSULTATION) {
            return 'Esta cita es presencial, no tiene sala de videollamada.';
        }

        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return match ($appointment->status) {
                Appointment::STATUS_COMPLETED => 'Esta teleconsulta ya fue cerrada por el profesional.',
                Appointment::STATUS_CANCELLED => 'Esta cita fue cancelada. Comunícate con la IPS para reagendar.',
                default => 'Esta cita ya no está activa. Comunícate con la IPS para reagendar.',
            };
        }

        [$opensAt, $closesAt] = $this->joinWindow($appointment);

        if (now()->lt($opensAt)) {
            $minutesBefore = (int) config('teleconsultation.join_window.minutes_before');

            return "La sala se abre {$minutesBefore} minutos antes de la hora de tu cita.";
        }

        if (now()->gt($closesAt)) {
            return 'La sala de esta teleconsulta ya se cerró. Comunícate con la IPS para reagendar.';
        }

        return null;
    }

    /**
     * Lo mismo, dicho para el profesional: sin instrucciones de reagendar y sin
     * tratarlo como si la cita fuera suya.
     */
    public function doctorJoinBlockedReason(Appointment $appointment): ?string
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return match ($appointment->status) {
                Appointment::STATUS_COMPLETED => 'Teleconsulta ya cerrada.',
                Appointment::STATUS_CANCELLED => 'Cita cancelada.',
                default => 'La cita ya no está activa.',
            };
        }

        [$opensAt, $closesAt] = $this->joinWindow($appointment);

        if (now()->lt($opensAt)) {
            $minutesBefore = (int) config('teleconsultation.join_window.minutes_before');

            return "La sala abre {$minutesBefore} minutos antes.";
        }

        if (now()->gt($closesAt)) {
            return 'La sala ya se cerró.';
        }

        return null;
    }

    /**
     * Franja en la que la sala está abierta. Es la misma para el paciente y para
     * el profesional: entrar antes solo mostraría una sala vacía.
     *
     * copy() porque Carbon es mutable y restarle minutos al atributo corrompería
     * la fecha de la cita en memoria.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function joinWindow(Appointment $appointment): array
    {
        return [
            $appointment->scheduled_at->copy()->subMinutes((int) config('teleconsultation.join_window.minutes_before')),
            $appointment->scheduled_at->copy()->addMinutes((int) config('teleconsultation.join_window.minutes_after')),
        ];
    }

    public function patientCanJoin(Appointment $appointment): bool
    {
        return $this->joinBlockedReason($appointment) === null;
    }

    /**
     * Teleconsulta del paciente que ya se puede abrir ahora mismo, si la hay.
     *
     * Una consulta que empezó hace diez minutos deja de ser "la próxima cita",
     * y es justo cuando el paciente más necesita el acceso a la sala.
     */
    public function joinableForPatient(Patient $patient): ?Appointment
    {
        $minutesBefore = (int) config('teleconsultation.join_window.minutes_before');
        $minutesAfter = (int) config('teleconsultation.join_window.minutes_after');

        return $patient->appointments()
            ->with('doctor:id,name')
            ->where('type', Appointment::TYPE_TELECONSULTATION)
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereBetween('scheduled_at', [now()->subMinutes($minutesAfter), now()->addMinutes($minutesBefore)])
            ->orderBy('scheduled_at')
            ->first(['id', 'doctor_id', 'scheduled_at', 'status', 'type']);
    }

    private function generateRoomName(): string
    {
        return 'nefrochoco-'.Str::uuid()->toString();
    }
}
