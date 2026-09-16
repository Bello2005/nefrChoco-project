<?php

namespace App\Support\ClinicalRules;

use App\Enums\VitalSignType;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Señales clínicas de un paciente, reunidas una sola vez.
 *
 * Todas salen de cálculos que ya existen en el sistema: `risk_level` lo guardó
 * el instrumento al aplicarse, y el estado de cada medición lo resuelve el enum
 * VitalSignType contra su rango de referencia. Aquí no se calcula riesgo nuevo.
 *
 * Se arma una vez por paciente y se pasa a todas las reglas para que ninguna
 * vuelva a consultar la base por su cuenta.
 */
final class PatientSignals
{
    /**
     * @param  Collection<int, VitalSign>  $recentVitalSigns  ordenadas de más reciente a más antigua
     * @param  Collection<int, ClinicalForm>  $renalControls  ordenados por fecha de laboratorio, el más reciente primero
     */
    private function __construct(
        public readonly Patient $patient,
        public readonly ?ClinicalForm $diabetesScreening,
        public readonly ?ClinicalForm $adherence,
        public readonly ?string $ecntDiagnosis,
        public readonly Collection $recentVitalSigns,
        public readonly Collection $renalControls,
    ) {}

    /**
     * Los controles renales se reciben aparte y no se sacan de la relación
     * cargada: la ficha del paciente solo trae los cinco formularios más
     * recientes, y la evolución renal necesita la serie completa.
     *
     * @param  Collection<int, ClinicalForm>|null  $renalControls
     */
    public static function for(Patient $patient, ?Collection $renalControls = null): self
    {
        $forms = $patient->clinicalForms->sortByDesc('created_at');
        $since = now()->subDays((int) config('clinical_support.vital_signs.lookback_days'));

        return new self(
            patient: $patient,
            diabetesScreening: $forms->firstWhere('form_type', 'tamizaje_diabetes'),
            adherence: $forms->firstWhere('form_type', 'adherencia_tratamiento'),
            ecntDiagnosis: $patient->latestClinicalHistory?->ecnt_diagnosis,
            recentVitalSigns: $patient->vitalSigns
                ->filter(fn (VitalSign $sign) => $sign->recorded_at->greaterThanOrEqualTo($since))
                ->sortByDesc('recorded_at')
                ->values(),
            renalControls: $renalControls ?? collect(),
        );
    }

    /** Control renal más reciente por fecha de laboratorio. */
    public function latestRenalControl(): ?ClinicalForm
    {
        return $this->renalControls->first();
    }

    /** El control anterior, para poder comparar dos puntos. */
    public function previousRenalControl(): ?ClinicalForm
    {
        return $this->renalControls->get(1);
    }

    /** Fecha del examen, que no es la de captura del formulario. */
    public function labDate(ClinicalForm $control): CarbonImmutable
    {
        return CarbonImmutable::parse($control->answers['fecha_laboratorio']);
    }

    /** Última medición de ese tipo que quedó fuera del rango de referencia. */
    public function latestOutOfRange(VitalSignType $type): ?VitalSign
    {
        return $this->recentVitalSigns
            ->first(fn (VitalSign $sign) => $sign->type === $type->value && $sign->isOutOfRange());
    }

    /**
     * Lecturas fuera de rango seguidas, contando desde la más reciente.
     *
     * Se corta en cuanto aparece una medición normal: una lectura alta aislada
     * entre controles normales puede ser un error de medición en casa, y no es
     * lo mismo que una tendencia sostenida.
     *
     * @return Collection<int, VitalSign>
     */
    public function outOfRangeStreak(VitalSignType $type): Collection
    {
        return $this->recentVitalSigns
            ->filter(fn (VitalSign $sign) => $sign->type === $type->value)
            ->values()
            ->takeWhile(fn (VitalSign $sign) => $sign->isOutOfRange());
    }

    /** @return array<int, VitalSignType> */
    public function typesWithReadings(): array
    {
        return $this->recentVitalSigns
            ->pluck('type')
            ->unique()
            ->map(fn (string $type) => VitalSignType::tryFrom($type))
            ->filter()
            ->values()
            ->all();
    }

    public function hasDiagnosedEcnt(): bool
    {
        return filled($this->ecntDiagnosis);
    }
}
