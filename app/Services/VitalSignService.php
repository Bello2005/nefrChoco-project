<?php

namespace App\Services;

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Notifications\VitalSignOutOfRangeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

// TODO doc: guia-usuario.md no explica que la presión se registra como
// par sistólica/diastólica en un mismo envío.
class VitalSignService
{
    public function record(Patient $patient, User $author, array $data): VitalSign
    {
        $type = VitalSignType::from($data['type']);
        $clientUuid = $data['client_uuid'] ?? null;

        // La presión arterial llega como un par en un mismo envío.
        if ($type === VitalSignType::BloodPressure && filled($data['value_diastolic'] ?? null)) {
            return $this->recordBloodPressure($patient, $author, $data, $clientUuid);
        }

        $reading = $this->persist($patient, $author, $type, (float) $data['value'], $data, $clientUuid);

        // Solo se avisa si acaba de crearse: un reenvío de la cola no debe
        // volver a alertar al médico por una medición que ya revisó.
        if ($reading->wasRecentlyCreated) {
            $this->notifyIfOutOfRange([$reading]);
        }

        return $reading;
    }

    /**
     * Sistólica y diastólica: dos lecturas, un solo envío.
     *
     * Se guardan como dos filas para no romper el modelo de "un tipo, un valor,
     * un rango" del que dependen las gráficas y el panel de alertas, pero
     * dentro de una transacción: al ser una única petición, no existe el caso
     * de que una se sincronice y la otra quede huérfana.
     */
    private function recordBloodPressure(Patient $patient, User $author, array $data, ?string $clientUuid): VitalSign
    {
        return DB::transaction(function () use ($patient, $author, $data, $clientUuid) {
            $systolic = $this->persist(
                $patient, $author, VitalSignType::BloodPressure,
                (float) $data['value'], $data, $this->pairKey($clientUuid, 'sistolica'),
            );

            $diastolic = $this->persist(
                $patient, $author, VitalSignType::BloodPressureDiastolic,
                (float) $data['value_diastolic'], $data, $this->pairKey($clientUuid, 'diastolica'),
            );

            // Un solo aviso por toma, aunque las dos cifras salgan de rango.
            $this->notifyIfOutOfRange(array_filter(
                [$systolic, $diastolic],
                fn (VitalSign $reading) => $reading->wasRecentlyCreated,
            ));

            return $systolic;
        });
    }

    /**
     * Clave de idempotencia derivada, una por fila del par.
     *
     * Se deriva con UUID v5 en lugar de concatenar un sufijo al `client_uuid`
     * porque la columna es de tipo uuid nativo de PostgreSQL: un valor como
     * "…:sistolica" no es un uuid válido y la base lo rechazaría. La versión 5
     * es determinista, así que un reenvío de la cola vuelve a producir
     * exactamente las mismas dos claves y ninguna fila se duplica.
     *
     * El frontend sigue mandando un solo `client_uuid` por envío.
     */
    private function pairKey(?string $clientUuid, string $componente): ?string
    {
        if ($clientUuid === null) {
            return null;
        }

        return Uuid::uuid5(Uuid::NAMESPACE_OID, $clientUuid.':'.$componente)->toString();
    }

    private function persist(Patient $patient, User $author, VitalSignType $type, float $value, array $data, ?string $clientUuid): VitalSign
    {
        $attributes = [
            'recorded_by' => $author->id,
            'type' => $type->value,
            'value' => $value,
            // La unidad la define el tipo de signo, no quien digita: evita
            // que dos registros del mismo signo lleguen en unidades distintas.
            'unit' => $type->unit(),
            'recorded_at' => $data['recorded_at'] ?? now(),
            'notes' => $data['notes'] ?? null,
        ];

        if ($clientUuid === null) {
            return $patient->vitalSigns()->create($attributes);
        }

        // Reenvío desde la cola offline: si el dispositivo ya había logrado
        // guardar esta medición, se devuelve la existente en vez de duplicarla.
        return $patient->vitalSigns()->firstOrCreate(['client_uuid' => $clientUuid], $attributes);
    }

    /** @param  array<int, VitalSign>  $readings */
    private function notifyIfOutOfRange(array $readings): void
    {
        $outOfRange = array_values(array_filter(
            $readings,
            fn (VitalSign $reading) => $reading->isOutOfRange(),
        ));

        if ($outOfRange === []) {
            return;
        }

        $doctor = $this->treatingDoctor($outOfRange[0]->patient);

        $doctor?->notify(new VitalSignOutOfRangeNotification($outOfRange));
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

    /**
     * Últimas lecturas fuera de rango, para el panel de alertas del médico.
     *
     * El rango se evalúa en SQL y no en PHP: antes se traían las 80 mediciones
     * más recientes y se filtraban en memoria, así que una medición alarmante
     * dejaba de verse en cuanto 80 mediciones normales la desplazaban. Con 150
     * registros de demostración ya había 41 lecturas fuera de rango y el panel
     * solo alcanzaba a mostrar 31.
     *
     * Los umbrales siguen viniendo del enum, que es su única fuente.
     *
     * Un par de presión con ambas cifras fuera de rango aparece aquí como dos
     * renglones, uno por cada lectura. Es deliberado: el panel lista mediciones,
     * no tomas, y agrupar el par escondería que las dos están alteradas. La
     * notificación sí es una sola, porque ahí lo que se evita es el ruido.
     */
    public function outOfRangeAlerts(int $limit = 8): Collection
    {
        return VitalSign::query()
            ->with('patient:id,full_name')
            ->where(function ($query) {
                foreach (VitalSignType::cases() as $type) {
                    $range = $type->referenceRange();

                    $query->orWhere(fn ($byType) => $byType
                        ->where('type', $type->value)
                        ->where(fn ($outside) => $outside
                            ->where('value', '<', $range['min'])
                            ->orWhere('value', '>', $range['max'])));
                }
            })
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }
}
