<?php

namespace App\Services;

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Notifications\VitalSignOutOfRangeNotification;
use Illuminate\Support\Collection;

class VitalSignService
{
    public function record(Patient $patient, User $author, array $data): VitalSign
    {
        $type = VitalSignType::from($data['type']);

        $attributes = [
            'recorded_by' => $author->id,
            'type' => $type->value,
            'value' => $data['value'],
            // La unidad la define el tipo de signo, no quien digita: evita
            // que dos registros del mismo signo lleguen en unidades distintas.
            'unit' => $type->unit(),
            'recorded_at' => $data['recorded_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ];

        // Reenvío desde la cola offline: si el dispositivo ya había logrado
        // guardar esta medición, se devuelve la existente en vez de duplicarla.
        if (! empty($data['client_uuid'])) {
            $reading = $patient->vitalSigns()->firstOrCreate(
                ['client_uuid' => $data['client_uuid']],
                $attributes,
            );

            // Solo se avisa si acaba de crearse: un reenvío de la cola no debe
            // volver a alertar al médico por una medición que ya revisó.
            if ($reading->wasRecentlyCreated) {
                $this->notifyIfOutOfRange($reading);
            }

            return $reading;
        }

        $reading = $patient->vitalSigns()->create($attributes);

        $this->notifyIfOutOfRange($reading);

        return $reading;
    }

    private function notifyIfOutOfRange(VitalSign $vitalSign): void
    {
        if (! $vitalSign->isOutOfRange()) {
            return;
        }

        $doctor = $this->treatingDoctor($vitalSign->patient);

        $doctor?->notify(new VitalSignOutOfRangeNotification($vitalSign));
    }

    /** Médico de la cita más reciente del paciente; es quien lleva su seguimiento. */
    private function treatingDoctor(Patient $patient): ?User
    {
        return $patient->appointments()
            ->latest('scheduled_at')
            ->with('doctor')
            ->first()
            ?->doctor;
    }

    /**
     * Series por tipo de signo, listas para graficar en orden cronológico.
     *
     * @return array<int, array<string, mixed>>
     */
    public function seriesFor(Patient $patient, int $limitPerType = 12): array
    {
        $readings = $patient->vitalSigns()
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('type');

        return $readings
            ->map(function (Collection $group, string $type) use ($limitPerType) {
                $enum = VitalSignType::tryFrom($type);
                $recent = $group->slice(-$limitPerType)->values();
                $latest = $recent->last();

                return [
                    'type' => $type,
                    'label' => $enum?->label() ?? $type,
                    'shortLabel' => $enum?->shortLabel() ?? $type,
                    'unit' => $enum?->unit() ?? '',
                    'range' => $enum?->referenceRange(),
                    'latest' => $latest ? (float) $latest->value : null,
                    'status' => $latest?->status() ?? 'normal',
                    'points' => $recent->map(fn (VitalSign $sign) => [
                        'label' => $sign->recorded_at->format('d/m'),
                        'value' => (float) $sign->value,
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    /** Últimas lecturas fuera de rango, para el panel de alertas del médico. */
    public function outOfRangeAlerts(int $limit = 8): Collection
    {
        return VitalSign::with('patient:id,full_name')
            ->latest('recorded_at')
            ->limit(80)
            ->get()
            ->filter(fn (VitalSign $sign) => $sign->isOutOfRange())
            ->take($limit)
            ->values();
    }
}
