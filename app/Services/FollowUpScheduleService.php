<?php

namespace App\Services;

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\VitalSign;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Frecuencia mínima de seguimiento remoto por nivel de riesgo
 * (Res. 1644 de 2026, art. 19 par. 1).
 *
 * Compara la última medición de cada signo vital con el máximo de días que
 * permite config('vital_signs.max_days_without_reading') para el nivel del
 * paciente. Mientras todos esos valores sigan en null (pendientes de validar
 * con la médica), la función está inactiva y no marca a nadie.
 */
class FollowUpScheduleService
{
    public function isActive(): bool
    {
        return collect(config('vital_signs.max_days_without_reading', []))
            ->flatten()
            ->contains(fn ($days) => $days !== null);
    }

    /**
     * Signos vitales con el control vencido para este paciente.
     *
     * @return Collection<int, array{type: string, label: string, maxDays: int, lastReadingAt: ?CarbonInterface}>
     */
    public function overdueFor(Patient $patient): Collection
    {
        $level = $patient->followUpRiskLevel();

        if ($level === null) {
            return collect();
        }

        $limits = collect(config("vital_signs.max_days_without_reading.{$level->value}", []))
            ->filter(fn ($days) => $days !== null);

        if ($limits->isEmpty()) {
            return collect();
        }

        $lastReadings = VitalSign::query()
            ->where('patient_id', $patient->id)
            ->whereIn('type', $limits->keys())
            ->selectRaw('type, max(recorded_at) as last_recorded_at')
            ->groupBy('type')
            ->pluck('last_recorded_at', 'type');

        return $limits
            ->map(function ($maxDays, string $type) use ($lastReadings) {
                $last = isset($lastReadings[$type]) ? now()->parse($lastReadings[$type]) : null;
                $isOverdue = $last === null || $last->lt(now()->subDays((int) $maxDays));

                return $isOverdue ? [
                    'type' => $type,
                    'label' => VitalSignType::tryFrom($type)?->label() ?? $type,
                    'maxDays' => (int) $maxDays,
                    'lastReadingAt' => $last,
                ] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Pacientes con al menos un control vencido.
     *
     * El nivel va cifrado, así que no se puede filtrar en SQL: se recorre el
     * padrón con nivel asignado. Para el tamaño del programa alcanza.
     *
     * @return Collection<int, array{patient: Patient, overdue: Collection}>
     */
    public function overduePatients(): Collection
    {
        if (! $this->isActive()) {
            return collect();
        }

        return Patient::query()
            ->whereNotNull('follow_up_risk_level')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Patient $patient) => ['patient' => $patient, 'overdue' => $this->overdueFor($patient)])
            ->filter(fn (array $row) => $row['overdue']->isNotEmpty())
            ->values();
    }
}
